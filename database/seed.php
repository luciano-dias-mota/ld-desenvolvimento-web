<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Seed disponível somente via linha de comando.\n");
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$appEnv = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));

if (in_array($appEnv, ['production', 'prod'], true)) {
    exit("Seed destrutivo bloqueado em produção.\n");
}

if (!in_array('--force', $argv ?? [], true)) {
    exit(
        "Este seed apaga os dados acadêmicos existentes.\n"
        . "Execute com --force somente em ambiente de desenvolvimento.\n"
    );
}

$contentFile = __DIR__ . '/seeds/curso_php_laravel_producao.json';

if (!is_file($contentFile)) {
    throw new RuntimeException(
        'Arquivo de conteúdo não encontrado: database/seeds/curso_php_laravel_producao.json'
    );
}

$json = file_get_contents($contentFile);

if ($json === false) {
    throw new RuntimeException('Não foi possível ler o arquivo de conteúdo do curso.');
}

$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

validateCourseData($data);

$db = Database::getInstance()->getConnection();

$tables = [
    'certificates',
    'user_module_tests',
    'user_exercise_submissions',
    'user_lesson_progress',
    'test_questions',
    'module_tests',
    'exercises',
    'lessons',
    'modules',
    'courses',
];

$db->beginTransaction();

try {
    foreach ($tables as $table) {
        $db->exec("DELETE FROM `{$table}`");
    }

    $courseId = insertCourse(
        $db,
        (string) $data['title'],
        (string) $data['slug'],
        (string) $data['description'],
        (string) $data['status']
    );

    foreach ($data['modules'] as $moduleData) {
        $moduleId = insertModule($db, $courseId, $moduleData);

        foreach ($moduleData['lessons'] as $lessonData) {
            $lessonId = insertLesson($db, $moduleId, $lessonData);
            insertExercise($db, $lessonId, $lessonData['exercise']);
        }

        $testId = insertModuleTest($db, $moduleId, $moduleData['test']);

        foreach ($moduleData['test']['questions'] as $questionData) {
            insertTestQuestion($db, $testId, $questionData);
        }

        echo sprintf(
            "Módulo %d - %s inserido com sucesso.\n",
            (int) $moduleData['module_number'],
            (string) $moduleData['title']
        );
    }

    $db->commit();

    echo "\nSeed concluído com sucesso.\n";
    echo "Curso: {$data['title']}\n";
    echo 'Módulos: ' . count($data['modules']) . "\n";
    echo 'Aulas: ' . countLessons($data['modules']) . "\n";
    echo 'Exercícios: ' . countLessons($data['modules']) . "\n";
    echo 'Questões de prova: ' . countQuestions($data['modules']) . "\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Falha no seed: ' . $e->getMessage());
    fwrite(STDERR, "Falha ao executar o seed. Consulte o log da aplicação.\n");
    exit(1);
}

function validateCourseData(array $data): void
{
    foreach (['title', 'slug', 'description', 'status', 'modules'] as $field) {
        if (!array_key_exists($field, $data)) {
            throw new RuntimeException("Conteúdo inválido: campo {$field} ausente.");
        }
    }

    if (!is_array($data['modules']) || $data['modules'] === []) {
        throw new RuntimeException('Conteúdo inválido: nenhum módulo encontrado.');
    }

    foreach ($data['modules'] as $module) {
        foreach (
            ['module_number', 'title', 'slug', 'xp_reward', 'lessons', 'test']
            as $field
        ) {
            if (!array_key_exists($field, $module)) {
                throw new RuntimeException(
                    "Módulo inválido: campo {$field} ausente."
                );
            }
        }

        if (!is_array($module['lessons']) || $module['lessons'] === []) {
            throw new RuntimeException(
                "Módulo {$module['module_number']} não possui aulas."
            );
        }

        foreach ($module['lessons'] as $lesson) {
            foreach (
                [
                    'title',
                    'slug',
                    'lesson_number',
                    'estimated_minutes',
                    'xp_reward',
                    'content',
                    'exercise',
                ]
                as $field
            ) {
                if (!array_key_exists($field, $lesson)) {
                    throw new RuntimeException(
                        "Aula inválida no módulo {$module['module_number']}: "
                        . "campo {$field} ausente."
                    );
                }
            }

            validateQuestionLike(
                $lesson['exercise'],
                "exercício da aula {$lesson['slug']}"
            );
        }

        if (
            !isset($module['test']['questions'])
            || !is_array($module['test']['questions'])
            || $module['test']['questions'] === []
        ) {
            throw new RuntimeException(
                "Módulo {$module['module_number']} não possui questões de prova."
            );
        }

        foreach ($module['test']['questions'] as $question) {
            validateQuestionLike(
                $question,
                "questão da prova do módulo {$module['module_number']}"
            );
        }
    }
}

function validateQuestionLike(array $item, string $context): void
{
    $type = (string) ($item['type'] ?? '');

    if (!in_array($type, ['multiple_choice', 'true_false'], true)) {
        throw new RuntimeException(
            "Tipo de questão inválido em {$context}: {$type}"
        );
    }

    $options = $item['options'] ?? null;
    $correctAnswer = (string) ($item['correct_answer'] ?? '');

    if (!is_array($options) || $options === []) {
        throw new RuntimeException("Alternativas ausentes em {$context}.");
    }

    if (!array_key_exists($correctAnswer, $options)) {
        throw new RuntimeException(
            "Resposta correta não corresponde a uma alternativa em {$context}."
        );
    }
}

function insertCourse(
    PDO $db,
    string $title,
    string $slug,
    string $description,
    string $status
): int {
    $stmt = $db->prepare(
        'INSERT INTO courses (title, slug, description, status)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$title, $slug, $description, $status]);

    return (int) $db->lastInsertId();
}

function insertModule(PDO $db, int $courseId, array $module): int
{
    $stmt = $db->prepare(
        "INSERT INTO modules
        (course_id, title, slug, description, module_number, xp_reward, status)
        VALUES (?, ?, ?, ?, ?, ?, 'published')"
    );

    $stmt->execute([
        $courseId,
        (string) $module['title'],
        (string) $module['slug'],
        (string) ($module['objective'] ?? ''),
        (int) $module['module_number'],
        (int) $module['xp_reward'],
    ]);

    return (int) $db->lastInsertId();
}

function insertLesson(PDO $db, int $moduleId, array $lesson): int
{
    $stmt = $db->prepare(
        "INSERT INTO lessons
        (
            module_id,
            title,
            slug,
            description,
            content,
            video_url,
            lesson_number,
            xp_reward,
            estimated_minutes,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'published')"
    );

    $stmt->execute([
        $moduleId,
        (string) $lesson['title'],
        (string) $lesson['slug'],
        (string) ($lesson['description'] ?? ''),
        (string) $lesson['content'],
        $lesson['video_url'] ?? null,
        (int) $lesson['lesson_number'],
        (int) $lesson['xp_reward'],
        (int) $lesson['estimated_minutes'],
    ]);

    return (int) $db->lastInsertId();
}

function insertExercise(PDO $db, int $lessonId, array $exercise): int
{
    $stmt = $db->prepare(
        "INSERT INTO exercises
        (
            lesson_id,
            title,
            exercise_type,
            question,
            options,
            correct_answer,
            xp_reward,
            exercise_number,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'published')"
    );

    $stmt->execute([
        $lessonId,
        (string) ($exercise['title'] ?? 'Exercício de fixação'),
        (string) $exercise['type'],
        (string) $exercise['question'],
        json_encode(
            $exercise['options'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
        (string) $exercise['correct_answer'],
        (int) $exercise['xp_reward'],
    ]);

    return (int) $db->lastInsertId();
}

function insertModuleTest(PDO $db, int $moduleId, array $test): int
{
    $stmt = $db->prepare(
        "INSERT INTO module_tests
        (
            module_id,
            title,
            description,
            passing_score,
            max_attempts,
            time_limit_minutes,
            xp_reward,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 'published')"
    );

    $stmt->execute([
        $moduleId,
        (string) ($test['title'] ?? 'Prova do módulo'),
        (string) ($test['description'] ?? 'Avaliação de conclusão do módulo.'),
        (float) $test['passing_score'],
        (int) $test['max_attempts'],
        (int) $test['time_limit_minutes'],
        (int) $test['xp_reward'],
    ]);

    return (int) $db->lastInsertId();
}

function insertTestQuestion(PDO $db, int $testId, array $question): void
{
    $stmt = $db->prepare(
        'INSERT INTO test_questions
        (
            module_test_id,
            question,
            question_type,
            options,
            correct_answer,
            points,
            question_number
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $testId,
        (string) $question['question'],
        (string) $question['type'],
        json_encode(
            $question['options'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
        (string) $question['correct_answer'],
        (float) $question['points'],
        (int) $question['question_number'],
    ]);
}

function countLessons(array $modules): int
{
    $total = 0;

    foreach ($modules as $module) {
        $total += count($module['lessons']);
    }

    return $total;
}

function countQuestions(array $modules): int
{
    $total = 0;

    foreach ($modules as $module) {
        $total += count($module['test']['questions']);
    }

    return $total;
}
