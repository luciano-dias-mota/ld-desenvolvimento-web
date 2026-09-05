<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class ModuleTest extends Model
{
    protected static string $table = 'module_tests';

    /**
     * Verifica se o usuário já passou neste teste.
     *
     * @param int $userId
     * @return bool
     */
    public function userPassed($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM user_module_tests 
             WHERE user_id = ? AND module_test_id = ? AND passed = 1"
        );
        $stmt->execute([$userId, $this->id]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Registra uma tentativa do usuário.
     *
     * @param int $userId
     * @param float $score
     * @param bool $passed
     * @return void
     */
    public function recordAttempt($userId, $score, $passed)
    {
        $db = Database::getInstance()->getConnection();

        // Conta tentativas existentes para calcular o número da tentativa
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM user_module_tests 
             WHERE user_id = ? AND module_test_id = ?"
        );
        $stmt->execute([$userId, $this->id]);
        $attemptNumber = (int) $stmt->fetchColumn() + 1;

        $xpEarned = $passed ? $this->xp_reward : 0;

        $stmt = $db->prepare(
            "INSERT INTO user_module_tests 
             (user_id, module_test_id, score, passed, xp_earned, attempt_number, started_at, completed_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$userId, $this->id, $score, $passed, $xpEarned, $attemptNumber]);

        if ($passed) {
            // Atualiza XP do usuário
            $stmt = $db->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
            $stmt->execute([$xpEarned, $userId]);

            // Marca o módulo como concluído (opcional, se tiver tabela de progresso de módulo)
            // Caso a plataforma use um campo "status" em modules:
            $module = Module::find($this->module_id);
            if ($module) {
                // Desbloqueia o próximo módulo
                $nextModule = Module::where('course_id', $module['course_id']);
                $nextModule = array_filter($nextModule, function($m) use ($module) {
                    return $m['module_number'] > $module['module_number'];
                });
                usort($nextModule, function($a, $b) {
                    return $a['module_number'] <=> $b['module_number'];
                });
                if (!empty($nextModule)) {
                    $next = $nextModule[0];
                    $db->prepare("UPDATE modules SET status = 'active' WHERE id = ?")
                       ->execute([$next['id']]);
                }

                // Verifica se é o último módulo para gerar certificado
                $allModules = Module::where('course_id', $module['course_id']);
                $maxNumber = max(array_column($allModules, 'module_number'));
                if ($module['module_number'] == $maxNumber) {
                    $cert = Certificate::getUserCertificate($userId, $module['course_id']);
                    if (!$cert) {
                        Certificate::createCertificate($userId, $module['course_id']);
                    }
                }
            }
        }
    }

    /**
     * Retorna as questões do teste em ordem.
     *
     * @return array
     */
    public function questions()
    {
        return TestQuestion::where('module_test_id', $this->id);
    }
}