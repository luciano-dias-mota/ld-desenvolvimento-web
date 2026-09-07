<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Seed disponível somente via linha de comando.');
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$appEnv = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));
if (in_array($appEnv, ['production', 'prod'], true)) {
    exit("Seed destrutivo bloqueado em produção.\n");
}

if (!in_array('--force', $argv ?? [], true)) {
    exit("Este seed apaga os dados acadêmicos existentes. Execute com --force somente em ambiente de desenvolvimento.\n");
}

$db = Database::getInstance()->getConnection();
$tables = ['certificates', 'user_module_tests', 'user_exercise_submissions', 'user_lesson_progress', 'test_questions', 'module_tests', 'exercises', 'lessons', 'modules', 'courses'];

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
$db->beginTransaction();

try {
    foreach ($tables as $table) {
        // Nomes vêm de uma lista fixa acima; não há entrada do usuário.
        $db->exec("DELETE FROM `{$table}`");
    }

// 1. Inserir curso
$courseId = insertCourse($db, 'LD Desenvolvimento Web', 'ld-desenvolvimento-web', 'Curso completo de desenvolvimento web com PHP, do zero ao framework Laravel.', 'published');

// Definição dos módulos com dados
$modules = [
    [
        'title' => 'Introdução ao PHP e Ambiente',
        'slug' => 'introducao-php-ambiente',
        'description' => 'Configuração do ambiente, sintaxe básica, primeiro script PHP.',
        'number' => 1,
        'xp' => 100,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'primeiro-script-php',
            'title' => 'Quiz - Primeiro Script',
            'type' => 'multiple_choice',
            'question' => 'Qual a extensão padrão de um arquivo PHP?',
            'options' => json_encode(['a' => '.php', 'b' => '.html', 'c' => '.phtml', 'd' => '.phps']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - Introdução ao PHP',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual tag é usada para abrir código PHP?', 'opts' => ['a' => '<?php', 'b' => '<%', 'c' => '<script language="php">', 'd' => '<?='], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual função exibe texto no navegador?', 'opts' => ['a' => 'echo', 'b' => 'print_r', 'c' => 'var_dump', 'd' => 'printf'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual servidor local é comumente usado com PHP?', 'opts' => ['a' => 'Apache', 'b' => 'Nginx', 'c' => 'XAMPP', 'd' => 'Todos os acima'], 'correct' => 'd', 'points' => 1],
                ['q' => 'O PHP é uma linguagem de script do lado do servidor?', 'opts' => ['a' => 'Sim', 'b' => 'Não'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual a função para incluir um arquivo?', 'opts' => ['a' => 'include', 'b' => 'require', 'c' => 'include_once', 'd' => 'Todas as anteriores'], 'correct' => 'd', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'Variáveis, Tipos e Operadores',
        'slug' => 'variaveis-tipos-operadores',
        'description' => 'Tipos de dados, variáveis, constantes, operadores aritméticos e lógicos.',
        'number' => 2,
        'xp' => 120,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'operadores-aritmeticos',
            'title' => 'Quiz - Operadores',
            'type' => 'multiple_choice',
            'question' => 'Qual operador soma dois números?',
            'options' => json_encode(['a' => '+', 'b' => '-', 'c' => '*', 'd' => '/']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - Variáveis e Operadores',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual o tipo de dado para números decimais?', 'opts' => ['a' => 'int', 'b' => 'float', 'c' => 'string', 'd' => 'bool'], 'correct' => 'b', 'points' => 1],
                ['q' => 'Qual função pode criar uma constante em tempo de execução?', 'opts' => ['a' => 'define()', 'b' => 'constant()', 'c' => 'isset()', 'd' => 'static()'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual operador lógico representa "E"?', 'opts' => ['a' => '&&', 'b' => '||', 'c' => '!', 'd' => 'xor'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual a saída de var_dump(true)?', 'opts' => ['a' => 'true', 'b' => '1', 'c' => 'bool(true)', 'd' => 'TRUE'], 'correct' => 'c', 'points' => 1],
                ['q' => 'Qual o operador de concatenação?', 'opts' => ['a' => '.', 'b' => '+', 'c' => '&', 'd' => '||'], 'correct' => 'a', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'Estruturas de Controle e Funções',
        'slug' => 'estruturas-controle-funcoes',
        'description' => 'Condicionais, loops, arrays, funções definidas pelo usuário.',
        'number' => 3,
        'xp' => 130,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'funcoes-php',
            'title' => 'Quiz - Funções',
            'type' => 'multiple_choice',
            'question' => 'Como declaramos uma função em PHP?',
            'options' => json_encode(['a' => 'function myFunc()', 'b' => 'def myFunc()', 'c' => 'func myFunc()', 'd' => 'create function myFunc()']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - Estruturas e Funções',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual estrutura de repetição executa pelo menos uma vez?', 'opts' => ['a' => 'while', 'b' => 'do-while', 'c' => 'for', 'd' => 'foreach'], 'correct' => 'b', 'points' => 1],
                ['q' => 'Qual palavra-chave retorna um valor de uma função?', 'opts' => ['a' => 'return', 'b' => 'break', 'c' => 'continue', 'd' => 'exit'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual função conta elementos de um array?', 'opts' => ['a' => 'count()', 'b' => 'size()', 'c' => 'length()', 'd' => 'sizeof()'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual a sintaxe do switch?', 'opts' => ['a' => 'switch($var) { case value: ... }', 'b' => 'switch($var) { case value ... }', 'c' => 'Ambas', 'd' => 'Nenhuma'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual loop itera sobre arrays?', 'opts' => ['a' => 'for', 'b' => 'foreach', 'c' => 'while', 'd' => 'do-while'], 'correct' => 'b', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'Formulários e Validação de Dados',
        'slug' => 'formularios-validacao',
        'description' => 'Receba dados do usuário com segurança usando GET, POST, validação e proteção contra XSS.',
        'number' => 4,
        'xp' => 140,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'validacao-php',
            'title' => 'Quiz - Validação',
            'type' => 'multiple_choice',
            'question' => 'Qual função filtra variáveis para validação?',
            'options' => json_encode(['a' => 'filter_var()', 'b' => 'validate()', 'c' => 'sanitize()', 'd' => 'check()']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - Formulários e Validação',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual método HTTP envia dados no corpo da requisição?', 'opts' => ['a' => 'GET', 'b' => 'POST', 'c' => 'PUT', 'd' => 'DELETE'], 'correct' => 'b', 'points' => 1],
                ['q' => 'Como acessar dados POST em PHP?', 'opts' => ['a' => '$_GET', 'b' => '$_POST', 'c' => '$_REQUEST', 'd' => '$_SESSION'], 'correct' => 'b', 'points' => 1],
                ['q' => 'Qual função escapa caracteres especiais HTML?', 'opts' => ['a' => 'htmlspecialchars()', 'b' => 'htmlentities()', 'c' => 'addslashes()', 'd' => 'strip_tags()'], 'correct' => 'a', 'points' => 1],
                ['q' => 'O que é XSS?', 'opts' => ['a' => 'Cross-Site Scripting', 'b' => 'Cross-Site Request Forgery', 'c' => 'SQL Injection', 'd' => 'Nenhuma'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual superglobal contém dados de formulário independente do método?', 'opts' => ['a' => '$_REQUEST', 'b' => '$_SERVER', 'c' => '$_ENV', 'd' => '$_COOKIE'], 'correct' => 'a', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'Banco de Dados com MySQL',
        'slug' => 'banco-dados-mysql',
        'description' => 'Crie bancos, tabelas e relacionamentos, e conecte o PHP ao MySQL com PDO.',
        'number' => 5,
        'xp' => 150,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'conexao-pdo',
            'title' => 'Quiz - PDO',
            'type' => 'multiple_choice',
            'question' => 'Qual classe PDO é usada para conexão?',
            'options' => json_encode(['a' => 'PDO', 'b' => 'PDOStatement', 'c' => 'PDOException', 'd' => 'MySQLi']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - MySQL e PDO',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual comando cria uma tabela?', 'opts' => ['a' => 'CREATE TABLE', 'b' => 'ALTER TABLE', 'c' => 'INSERT INTO', 'd' => 'UPDATE'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual função prepara uma consulta PDO?', 'opts' => ['a' => 'prepare()', 'b' => 'query()', 'c' => 'execute()', 'd' => 'fetch()'], 'correct' => 'a', 'points' => 1],
                ['q' => 'O que é SQL Injection?', 'opts' => ['a' => 'Injeção de código SQL malicioso', 'b' => 'Ataque XSS', 'c' => 'Acesso não autorizado', 'd' => 'Nenhuma'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual método executa uma consulta preparada?', 'opts' => ['a' => 'execute()', 'b' => 'exec()', 'c' => 'run()', 'd' => 'query()'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual driver PDO é usado para MySQL?', 'opts' => ['a' => 'mysql', 'b' => 'pdo_mysql', 'c' => 'mysqli', 'd' => 'sqlite'], 'correct' => 'a', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'CRUD Completo com PDO',
        'slug' => 'crud-pdo',
        'description' => 'Construa um sistema de cadastro completo: criar, listar, editar e excluir registros.',
        'number' => 6,
        'xp' => 160,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'deletar-registro',
            'title' => 'Quiz - DELETE',
            'type' => 'multiple_choice',
            'question' => 'Qual comando SQL remove registros?',
            'options' => json_encode(['a' => 'DELETE', 'b' => 'DROP', 'c' => 'REMOVE', 'd' => 'TRUNCATE']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - CRUD',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual SQL insere dados?', 'opts' => ['a' => 'INSERT INTO', 'b' => 'UPDATE', 'c' => 'SELECT', 'd' => 'DELETE'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual SQL atualiza registros?', 'opts' => ['a' => 'UPDATE', 'b' => 'ALTER', 'c' => 'MODIFY', 'd' => 'CHANGE'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual cláusula filtra resultados?', 'opts' => ['a' => 'WHERE', 'b' => 'HAVING', 'c' => 'GROUP BY', 'd' => 'ORDER BY'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual função retorna o último ID inserido?', 'opts' => ['a' => 'lastInsertId()', 'b' => 'insert_id()', 'c' => 'getLastId()', 'd' => 'lastId()'], 'correct' => 'a', 'points' => 1],
                ['q' => 'O que significa CRUD?', 'opts' => ['a' => 'Create, Read, Update, Delete', 'b' => 'Create, Remove, Update, Delete', 'c' => 'Create, Read, Upgrade, Delete', 'd' => 'None'], 'correct' => 'a', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'Composer e Arquitetura MVC',
        'slug' => 'composer-mvc',
        'description' => 'Gerenciando dependências com Composer e organizando projetos grandes com o padrão MVC.',
        'number' => 7,
        'xp' => 170,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'criando-composer-json',
            'title' => 'Quiz - Composer',
            'type' => 'multiple_choice',
            'question' => 'Qual comando instala as dependências registradas no composer.lock?',
            'options' => json_encode(['a' => 'composer install', 'b' => 'composer update', 'c' => 'composer init', 'd' => 'composer dump-autoload']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - Composer e MVC',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual arquivo define dependências no Composer?', 'opts' => ['a' => 'composer.json', 'b' => 'composer.lock', 'c' => 'package.json', 'd' => 'dependencies.json'], 'correct' => 'a', 'points' => 1],
                ['q' => 'O que é MVC?', 'opts' => ['a' => 'Model-View-Controller', 'b' => 'Module-View-Controller', 'c' => 'Model-Variable-Controller', 'd' => 'None'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual pasta contém as views no MVC?', 'opts' => ['a' => 'Views', 'b' => 'Models', 'c' => 'Controllers', 'd' => 'Core'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual comando cria um projeto com Composer?', 'opts' => ['a' => 'composer create-project', 'b' => 'composer init', 'c' => 'composer start', 'd' => 'composer new'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual namespace é usado para autoload PSR-4?', 'opts' => ['a' => 'psr-4', 'b' => 'psr-0', 'c' => 'classmap', 'd' => 'files'], 'correct' => 'a', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'Laravel: Primeiros Passos',
        'slug' => 'laravel-primeiros-passos',
        'description' => 'Instale o Laravel e aprenda sua estrutura de pastas, rotas e controllers.',
        'number' => 8,
        'xp' => 180,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'rotas-laravel',
            'title' => 'Quiz - Rotas',
            'type' => 'multiple_choice',
            'question' => 'Onde definimos rotas no Laravel?',
            'options' => json_encode(['a' => 'routes/web.php', 'b' => 'config/routes.php', 'c' => 'app/Http/routes.php', 'd' => 'routes/api.php']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - Laravel Básico',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual comando cria um novo projeto Laravel?', 'opts' => ['a' => 'composer create-project laravel/laravel', 'b' => 'laravel new', 'c' => 'Ambos', 'd' => 'Nenhum'], 'correct' => 'c', 'points' => 1],
                ['q' => 'Onde ficam os controllers?', 'opts' => ['a' => 'app/Http/Controllers', 'b' => 'app/Controllers', 'c' => 'src/Controllers', 'd' => 'controllers/'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual arquivo contém as variáveis de ambiente?', 'opts' => ['a' => '.env', 'b' => 'config/app.php', 'c' => 'config/database.php', 'd' => 'env.example'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual comando executa o servidor de desenvolvimento?', 'opts' => ['a' => 'php artisan serve', 'b' => 'php -S', 'c' => 'composer serve', 'd' => 'serve'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual pasta contém as views Blade?', 'opts' => ['a' => 'resources/views', 'b' => 'public/views', 'c' => 'app/views', 'd' => 'storage/views'], 'correct' => 'a', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'Laravel: Blade e Banco de Dados',
        'slug' => 'laravel-blade-database',
        'description' => 'Crie interfaces com Blade e versione o banco de dados com migrações, seeders e factories.',
        'number' => 9,
        'xp' => 190,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'blade-templates',
            'title' => 'Quiz - Blade',
            'type' => 'multiple_choice',
            'question' => 'Qual diretiva do Blade exibe uma variável?',
            'options' => json_encode(['a' => '{{ $var }}', 'b' => '{!! $var !!}', 'c' => '@yield(\"var\")', 'd' => '@include(\"var\")']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - Blade e Migrações',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual comando cria uma migração?', 'opts' => ['a' => 'php artisan make:migration', 'b' => 'php artisan migrate:make', 'c' => 'php artisan create:migration', 'd' => 'php artisan new:migration'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual comando executa migrações?', 'opts' => ['a' => 'php artisan migrate', 'b' => 'php artisan db:migrate', 'c' => 'php artisan schema:update', 'd' => 'php artisan up'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual diretiva Blade estende um layout?', 'opts' => ['a' => '@extends', 'b' => '@include', 'c' => '@layout', 'd' => '@section'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Onde ficam os seeders?', 'opts' => ['a' => 'database/seeders', 'b' => 'database/seeds', 'c' => 'app/Seeds', 'd' => 'config/seeds'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual comando roda os seeders?', 'opts' => ['a' => 'php artisan db:seed', 'b' => 'php artisan seed', 'c' => 'php artisan make:seeder', 'd' => 'php artisan migrate:fresh --seed'], 'correct' => 'a', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'Laravel: Eloquent e CRUD',
        'slug' => 'laravel-eloquent-crud',
        'description' => 'Use o Eloquent ORM para criar um CRUD completo e relacionamentos entre tabelas.',
        'number' => 10,
        'xp' => 200,
        'lessons' => 10,
        'exercise' => [
            'lesson_slug' => 'eloquent-orm',
            'title' => 'Quiz - Eloquent',
            'type' => 'multiple_choice',
            'question' => 'Qual classe devemos estender para criar um modelo Eloquent?',
            'options' => json_encode(['a' => 'Illuminate\Database\Eloquent\Model', 'b' => 'App\Models\Model', 'c' => 'Eloquent', 'd' => 'DB']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - Eloquent e CRUD',
            'passing_score' => 70,
            'questions' => [
                ['q' => 'Qual método Eloquent insere um registro?', 'opts' => ['a' => 'create()', 'b' => 'insert()', 'c' => 'save()', 'd' => 'store()'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual método atualiza um registro?', 'opts' => ['a' => 'update()', 'b' => 'save()', 'c' => 'both', 'd' => 'modify()'], 'correct' => 'c', 'points' => 1],
                ['q' => 'Como definimos relacionamento belongsTo?', 'opts' => ['a' => 'return $this->belongsTo(Model::class)', 'b' => 'return $this->hasOne(Model::class)', 'c' => 'return $this->hasMany(Model::class)', 'd' => 'return $this->belongsToMany(Model::class)'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual método obtém todos os registros?', 'opts' => ['a' => 'all()', 'b' => 'get()', 'c' => 'fetch()', 'd' => 'select()'], 'correct' => 'a', 'points' => 1],
                ['q' => 'Qual comando cria um model?', 'opts' => ['a' => 'php artisan make:model', 'b' => 'php artisan model:create', 'c' => 'php artisan new:model', 'd' => 'php artisan generate:model'], 'correct' => 'a', 'points' => 1]
            ]
        ]
    ],
    [
        'title' => 'Projeto Final: Sistema de Atendimento',
        'slug' => 'projeto-final-helpdesk',
        'description' => 'Aplique tudo o que aprendeu construindo um sistema de helpdesk completo, do zero ao deploy.',
        'number' => 11,
        'xp' => 250,
        'lessons' => 12,
        'exercise' => [
            'lesson_slug' => 'planejamento-helpdesk',
            'title' => 'Quiz - Planejamento',
            'type' => 'multiple_choice',
            'question' => 'Qual etapa vem primeiro no desenvolvimento?',
            'options' => json_encode(['a' => 'Levantamento de requisitos', 'b' => 'Modelagem de dados', 'c' => 'Criação de interfaces', 'd' => 'Deploy']),
            'correct' => 'a',
            'xp' => 20
        ],
        'test' => [
            'title' => 'Prova - Projeto Final',
            'passing_score' => 80,
            'questions' => [
                ['q' => 'O que é um sistema de helpdesk?', 'opts' => ['a' => 'Atendimento ao cliente', 'b' => 'Gerenciamento de projetos', 'c' => 'Sistema de vendas', 'd' => 'Blog'], 'correct' => 'a', 'points' => 2],
                ['q' => 'Qual framework usamos?', 'opts' => ['a' => 'Laravel', 'b' => 'Symfony', 'c' => 'CodeIgniter', 'd' => 'CakePHP'], 'correct' => 'a', 'points' => 2],
                ['q' => 'O que é deploy?', 'opts' => ['a' => 'Publicar aplicação', 'b' => 'Testar aplicação', 'c' => 'Desenvolver', 'd' => 'Documentar'], 'correct' => 'a', 'points' => 2],
                ['q' => 'Qual tipo de relacionamento entre usuário e ticket?', 'opts' => ['a' => 'One-to-Many', 'b' => 'Many-to-Many', 'c' => 'One-to-One', 'd' => 'None'], 'correct' => 'a', 'points' => 2],
                ['q' => 'Qual comando faz o deploy?', 'opts' => ['a' => 'git push', 'b' => 'composer deploy', 'c' => 'php artisan deploy', 'd' => 'Não há comando padrão'], 'correct' => 'd', 'points' => 2]
            ]
        ]
    ]
];

// Inserir cada módulo
$moduleIds = [];
foreach ($modules as $modData) {
    $moduleId = insertModule($db, $courseId, $modData['title'], $modData['slug'], $modData['description'], $modData['number'], $modData['xp']);
    $moduleIds[$modData['slug']] = $moduleId;

    // Inserir aulas
    for ($i = 1; $i <= $modData['lessons']; $i++) {
        $lessonSlug = ($i === min(5, (int) $modData['lessons']))
            ? $modData['exercise']['lesson_slug']
            : 'aula-' . $i;
        $lessonTitle = 'Aula ' . $i . ' - ' . $modData['title'];
        // Conteúdo genérico (pode ser melhorado)
        $content = "<p>Nesta aula você aprenderá sobre <strong>{$modData['title']}</strong>. Este é um conteúdo de exemplo para a aula número $i do módulo \"{$modData['title']}\".</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec vel nisl non sapien tincidunt tincidunt.</p>";
        insertLesson($db, $moduleId, $lessonTitle, $lessonSlug, $content, $i, 10);
    }

    // Inserir exercício (na aula 5, por exemplo)
    $exLessonSlug = $modData['exercise']['lesson_slug'];
    $stmt = $db->prepare("SELECT id FROM lessons WHERE module_id = ? AND slug = ?");
    $stmt->execute([$moduleId, $exLessonSlug]);
    $lessonId = $stmt->fetchColumn();
    if ($lessonId) {
        insertExercise($db, $lessonId, $modData['exercise']['title'], $modData['exercise']['type'], $modData['exercise']['question'], $modData['exercise']['options'], $modData['exercise']['correct'], $modData['exercise']['xp'], 1);
    }

    // Inserir prova
    $testId = insertModuleTest($db, $moduleId, $modData['test']['title'], $modData['test']['passing_score'], 100);
    foreach ($modData['test']['questions'] as $index => $q) {
        insertTestQuestion(
            $db,
            $testId,
            $q['q'],
            'multiple_choice',
            json_encode($q['opts']),
            $q['correct'],
            $q['points'],
            $index + 1
        );
    }

    echo "Módulo \"{$modData['title']}\" inserido com sucesso.\n";
}

// modules.status representa PUBLICAÇÃO do conteúdo (draft/published).
// O desbloqueio é calculado individualmente por aluno a partir de user_module_tests.

    $db->commit();
    echo "Seed concluído com sucesso!\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Falha no seed: ' . $e->getMessage());
    fwrite(STDERR, "Falha ao executar o seed. Consulte o log da aplicação.\n");
    exit(1);
} finally {
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ======================== FUNÇÕES AUXILIARES ========================

function insertCourse($db, $title, $slug, $description, $status) {
    $stmt = $db->prepare("INSERT INTO courses (title, slug, description, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $slug, $description, $status]);
    return $db->lastInsertId();
}

function insertModule($db, $courseId, $title, $slug, $description, $number, $xp) {
    $stmt = $db->prepare("INSERT INTO modules (course_id, title, slug, description, module_number, xp_reward, status) VALUES (?, ?, ?, ?, ?, ?, 'published')");
    $stmt->execute([$courseId, $title, $slug, $description, $number, $xp]);
    return $db->lastInsertId();
}

function insertLesson($db, $moduleId, $title, $slug, $content, $number, $xp) {
    $stmt = $db->prepare("INSERT INTO lessons (module_id, title, slug, content, lesson_number, xp_reward, status) VALUES (?, ?, ?, ?, ?, ?, 'published')");
    $stmt->execute([$moduleId, $title, $slug, $content, $number, $xp]);
    return $db->lastInsertId();
}

function insertExercise($db, $lessonId, $title, $type, $question, $options, $correct, $xp, $number) {
    $stmt = $db->prepare("INSERT INTO exercises (lesson_id, title, exercise_type, question, options, correct_answer, xp_reward, exercise_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published')");
    $stmt->execute([$lessonId, $title, $type, $question, $options, $correct, $xp, $number]);
    return $db->lastInsertId();
}

function insertModuleTest($db, $moduleId, $title, $passingScore, $xp) {
    $stmt = $db->prepare("INSERT INTO module_tests (module_id, title, passing_score, xp_reward, status) VALUES (?, ?, ?, ?, 'published')");
    $stmt->execute([$moduleId, $title, $passingScore, $xp]);
    return $db->lastInsertId();
}

function insertTestQuestion($db, $testId, $question, $type, $options, $correct, $points, $questionNumber) {
    $stmt = $db->prepare("INSERT INTO test_questions (module_test_id, question, question_type, options, correct_answer, points, question_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$testId, $question, $type, $options, $correct, $points, $questionNumber]);
}