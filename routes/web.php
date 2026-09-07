<?php

/** @var \App\Core\Router $router */

// Home
$router->get('/', 'HomeController@index');

// Auth tradicional
$router->get('/login', 'AuthController@showLoginForm')->middleware('guest');
$router->post('/login', 'AuthController@login')->middleware('guest');
$router->get('/register', 'AuthController@showRegisterForm')->middleware('guest');
$router->post('/register', 'AuthController@register')->middleware('guest');
$router->post('/logout', 'AuthController@logout')->middleware('auth');

// Google Identity Services
$router->post('/auth/google', 'GoogleAuthController@login')->middleware('guest');

// Modo visitante: nenhuma conta é criada e nenhum progresso é gravado
$router->post('/visitante/entrar', 'AuthController@enterGuest')->middleware('guest');
$router->post('/visitante/sair', 'AuthController@exitGuest')->middleware('learning');

// Verificação de e-mail (opcional via .env/Brevo)
$router->get('/verificar-email/{token}', 'AuthController@verifyEmail');
$router->post('/verificacao-email/reenviar', 'AuthController@resendVerification')->middleware('auth');

// Dashboard / mapa: autenticado OU visitante
$router->get('/dashboard', 'DashboardController@index')->middleware('learning');

// Cursos
$router->get('/cursos/{courseSlug}/{moduleSlug}', 'CourseController@showModule')->middleware('learning');
$router->get('/cursos/{courseSlug}/{moduleSlug}/prova', 'ModuleTestController@show')->middleware('learning');
$router->post('/cursos/{courseSlug}/{moduleSlug}/prova', 'ModuleTestController@submit')->middleware('learning');

// Aulas
$router->get('/aulas/{courseSlug}/{moduleSlug}/{lessonSlug}', 'LessonController@show')->middleware('learning');
$router->post('/aulas/{courseSlug}/{moduleSlug}/{lessonSlug}/concluir', 'LessonController@complete')->middleware('learning');

// Exercícios
$router->get('/exercicios/{courseSlug}/{moduleSlug}/{lessonSlug}', 'ExerciseController@show')->middleware('learning');
$router->post('/exercicios/{courseSlug}/{moduleSlug}/{lessonSlug}', 'ExerciseController@submit')->middleware('learning');

// Certificado: SOMENTE conta autenticada
$router->get('/certificado/validar/{code}', 'CertificateController@validar');
$router->post('/certificado/{courseSlug}/emitir', 'CertificateController@issue')->middleware('auth');
$router->get('/certificado/{courseSlug}', 'CertificateController@show')->middleware('auth');
