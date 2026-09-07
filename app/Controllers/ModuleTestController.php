<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTest;

class ModuleTestController extends LearningController
{
    public function show(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();
        if (!$user && !$isGuest) $this->redirect('/register');

        [$course,$module,$test]=$this->resolveTest($courseSlug,$moduleSlug);

        if (!$isGuest) {
            if (!$this->canAccessModule((int)$user['id'],$module)) {
                Session::flash('error','Este módulo ainda está bloqueado.');
                $this->redirect('/dashboard#curso-'.rawurlencode($courseSlug));
            }
            if (!$this->hasCompletedAllLessons((int)$user['id'],(int)$module['id'])) {
                Session::flash('error','Conclua todas as aulas deste módulo antes de fazer a prova.');
                $this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug));
            }
            if ($this->hasPassedModule((int)$user['id'],(int)$module['id'])) {
                Session::flash('success','Você já foi aprovado neste módulo.');
                $this->redirect('/dashboard#curso-'.rawurlencode($courseSlug));
            }
        }

        $maxAttempts = $isGuest ? 0 : (isset($test['max_attempts']) && $test['max_attempts'] !== null ? max(0,(int)$test['max_attempts']) : 0);
        $attemptCount = $isGuest ? 0 : $this->countAttempts((int)$user['id'],(int)$test['id']);
        $testResult=Session::flash('test_result');
        $resultado=null;$passed=false;$score=0.0;
        if(is_array($testResult)&&(int)($testResult['test_id']??0)===(int)$test['id']){
            $resultado=$testResult;$passed=(bool)($testResult['passed']??false);$score=(float)($testResult['score']??0);
        }
        $canRetry=$isGuest || $maxAttempts===0 || $attemptCount<$maxAttempts;
        if(!$isGuest&&!$canRetry&&$resultado===null){
            Session::flash('error','Você atingiu o limite de tentativas desta prova.');
            $this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug));
        }

        $stmt=$this->db()->prepare('SELECT id, module_test_id, question, question_type, options, points, question_number FROM test_questions WHERE module_test_id = ? ORDER BY question_number ASC, id ASC');
        $stmt->execute([$test['id']]);
        $questions=$stmt->fetchAll();
        if($questions===[]){
            Session::flash('error','A prova deste módulo ainda não possui questões.');
            $this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug));
        }

        $this->view('cursos/prova',compact('course','module','test','questions','resultado','passed','score','attemptCount','maxAttempts','canRetry','isGuest'));
    }

    public function submit(string $courseSlug, string $moduleSlug): void
    {
        $user=Auth::user();$isGuest=Auth::isGuest();
        if(!$user&&!$isGuest)$this->redirect('/register');
        if(!$this->validateCsrf()){
            Session::flash('error','Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug).'/prova');
        }
        [$course,$module,$test]=$this->resolveTest($courseSlug,$moduleSlug);
        if(!$isGuest){
            if(!$this->canAccessModule((int)$user['id'],$module)){
                Session::flash('error','Este módulo ainda está bloqueado.');
                $this->redirect('/dashboard#curso-'.rawurlencode($courseSlug));
            }
            if(!$this->hasCompletedAllLessons((int)$user['id'],(int)$module['id'])){
                Session::flash('error','Conclua todas as aulas deste módulo antes de enviar a prova.');
                $this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug));
            }
        }

        $answers=$_POST['respostas']??[]; if(!is_array($answers))$answers=[];
        $stmt=$this->db()->prepare('SELECT id, module_test_id, question, question_type, options, correct_answer, points, question_number FROM test_questions WHERE module_test_id = ? ORDER BY question_number ASC, id ASC');
        $stmt->execute([$test['id']]);$questions=$stmt->fetchAll();
        if($questions===[]){Session::flash('error','A prova deste módulo ainda não possui questões.');$this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug));}
        $totalPoints=0.0;$earnedPoints=0.0;
        foreach($questions as $q){
            $points=max(0,(float)($q['points']??0));$totalPoints+=$points;
            $qid=(int)$q['id'];$ua=trim((string)($answers[$qid]??''));$ca=trim((string)($q['correct_answer']??''));
            if($ua!==''&&$ua===$ca)$earnedPoints+=$points;
        }
        if($totalPoints<=0){Session::flash('error','A prova está configurada incorretamente. Informe o administrador.');$this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug));}
        $score=round(($earnedPoints/$totalPoints)*100,2);$passed=$score>=(float)$test['passing_score'];

        if($isGuest){
            Session::flash('test_result',['test_id'=>(int)$test['id'],'score'=>$score,'passed'=>$passed,'guest'=>true]);
            $this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug).'/prova');
        }

        $this->db()->beginTransaction();
        try{
            $stmt=$this->db()->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');$stmt->execute([$user['id']]);
            $stmt=$this->db()->prepare('SELECT 1 FROM user_module_tests WHERE user_id = ? AND module_test_id = ? AND passed = 1 LIMIT 1');$stmt->execute([$user['id'],$test['id']]);
            if($stmt->fetchColumn()){$this->db()->commit();Session::flash('success','Você já havia sido aprovado neste módulo.');$this->redirect('/dashboard#curso-'.rawurlencode($courseSlug));}
            $stmt=$this->db()->prepare('SELECT COUNT(*) FROM user_module_tests WHERE user_id = ? AND module_test_id = ?');$stmt->execute([$user['id'],$test['id']]);$attemptCount=(int)$stmt->fetchColumn();
            $maxAttempts=isset($test['max_attempts'])&&$test['max_attempts']!==null?max(0,(int)$test['max_attempts']):0;
            if($maxAttempts>0&&$attemptCount>=$maxAttempts){$this->db()->commit();Session::flash('error','Você atingiu o limite de tentativas desta prova.');$this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug));}
            $attemptNumber=$attemptCount+1;$xpEarned=$passed?max(0,(int)($test['xp_reward']??0)):0;
            $stmt=$this->db()->prepare('INSERT INTO user_module_tests (user_id,module_test_id,score,passed,xp_earned,attempt_number,started_at,completed_at) VALUES (?,?,?,?,?,?,NOW(),NOW())');
            $stmt->execute([$user['id'],$test['id'],$score,(int)$passed,$xpEarned,$attemptNumber]);
            if($passed&&$xpEarned>0){$stmt=$this->db()->prepare('UPDATE users SET xp=xp+? WHERE id=?');$stmt->execute([$xpEarned,$user['id']]);}
            if($passed&&$this->progress()->isCourseComplete((int)$user['id'],(int)$course['id'])&&$this->canIssueCertificateFor($user)){
                if(!Certificate::getUserCertificate((int)$user['id'],(int)$course['id']))Certificate::createCertificate((int)$user['id'],(int)$course['id']);
            }
            $this->db()->commit();
        }catch(\Throwable $e){if($this->db()->inTransaction())$this->db()->rollBack();throw $e;}

        if($passed){Session::flash('success','Aprovado com '.$score.'%! Próxima fase liberada.');$this->redirect('/dashboard#curso-'.rawurlencode($courseSlug));}
        Session::flash('test_result',['test_id'=>(int)$test['id'],'score'=>$score,'passed'=>false,'guest'=>false]);
        $this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug).'/prova');
    }

    private function resolveTest(string $courseSlug,string $moduleSlug):array
    {
        $course=Course::firstWhere('slug',$courseSlug);if(!$course||($course['status']??'')!=='published')$this->notFound();
        $module=Module::firstWhereAll(['course_id'=>$course['id'],'slug'=>$moduleSlug,'status'=>'published']);if(!$module)$this->notFound();
        $test=ModuleTest::firstWhere('module_id',$module['id']);if(!$test||($test['status']??'')!=='published'){Session::flash('error','A prova deste módulo ainda não está disponível.');$this->redirect('/cursos/'.rawurlencode($courseSlug).'/'.rawurlencode($moduleSlug));}
        return[$course,$module,$test];
    }
    private function countAttempts(int $userId,int $testId):int{$stmt=$this->db()->prepare('SELECT COUNT(*) FROM user_module_tests WHERE user_id = ? AND module_test_id = ?');$stmt->execute([$userId,$testId]);return(int)$stmt->fetchColumn();}
}
