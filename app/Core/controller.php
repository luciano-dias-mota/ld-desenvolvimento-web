<?php

namespace App\Core;

abstract class Controller
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewPath = __DIR__ . '/../Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException("View {$view} não encontrada.");
        }

        if (str_starts_with($view, 'layouts/') || str_starts_with($view, 'errors/')) {
            // As views de erro atuais já são documentos HTML completos.
            require $viewPath;
            return;
        }

        if ($view === 'certificado/show') {
            // A view atual do certificado já é um documento HTML completo.
            require $viewPath;
            return;
        }

        if (str_starts_with($view, 'auth/') || $view === 'certificado/validar') {
            $layout = 'auth';
        } elseif (str_starts_with($view, 'admin/')) {
            $layout = 'main';
        } else {
            $layout = 'main';
        }

        $contentView = $viewPath;
        $layoutPath = __DIR__ . '/../Views/layouts/' . $layout . '.php';

        if (is_file($layoutPath)) {
            require $layoutPath;
            return;
        }

        require $viewPath;
    }

    protected function layout(string $layoutName): void
    {
        // Mantido apenas por compatibilidade com views antigas.
        // A seleção real do layout é feita em view().
    }

    protected function redirect(string $url, int $status = 302): void
    {
        if (headers_sent()) {
            throw new \RuntimeException('Não foi possível redirecionar: os cabeçalhos HTTP já foram enviados.');
        }

        $location = $url;
        if (str_starts_with($url, '/')) {
            if (function_exists('url')) {
                $location = url($url);
            } else {
                $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
                $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
                $location = (($basePath !== '' && $basePath !== '.' && $basePath !== '/') ? $basePath : '') . $url;
            }
        }

        header('Location: ' . $location, true, $status);
        exit;
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function csrfField(): string
    {
        $token = Session::get('csrf_token');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }

        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            . '">';
    }

    protected function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        $sessionToken = Session::get('csrf_token');

        if (!is_string($token) || !is_string($sessionToken) || $token === '' || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    protected function notFound(): void
    {
        http_response_code(404);
        $this->view('errors/404');
        exit;
    }

    /**
     * Define acesso ao módulo por usuário, sem alterar modules.status.
     * O primeiro módulo é liberado; os demais dependem da aprovação
     * do módulo imediatamente anterior.
     */
    protected function canAccessModule(int $userId, array $module): bool
    {
        $moduleNumber = (int) ($module['module_number'] ?? 0);
        $courseId = (int) ($module['course_id'] ?? 0);

        if ($courseId <= 0 || $moduleNumber <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT id
             FROM modules
             WHERE course_id = ? AND module_number < ?
             ORDER BY module_number DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$courseId, $moduleNumber]);
        $previousModuleId = $stmt->fetchColumn();

        if (!$previousModuleId) {
            return true;
        }

        return $this->hasPassedModule($userId, (int) $previousModuleId);
    }

    protected function hasPassedModule(int $userId, int $moduleId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM module_tests mt
             INNER JOIN user_module_tests umt ON umt.module_test_id = mt.id
             WHERE mt.module_id = ?
               AND umt.user_id = ?
               AND umt.passed = 1
             LIMIT 1'
        );
        $stmt->execute([$moduleId, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    protected function hasCompletedAllLessons(int $userId, int $moduleId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM lessons
             WHERE module_id = ? AND status = 'published'"
        );
        $stmt->execute([$moduleId]);
        $totalLessons = (int) $stmt->fetchColumn();

        if ($totalLessons === 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT l.id)
             FROM lessons l
             INNER JOIN user_lesson_progress ulp ON ulp.lesson_id = l.id
             WHERE l.module_id = ?
               AND l.status = 'published'
               AND ulp.user_id = ?
               AND ulp.completed = 1"
        );
        $stmt->execute([$moduleId, $userId]);
        $completedLessons = (int) $stmt->fetchColumn();

        return $completedLessons >= $totalLessons;
    }
}
