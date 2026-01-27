<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
if (current_user()) {
  header('Location: ' . $base_url . '/dashboard.php');
  exit;
}
include __DIR__ . '/components/header.php';
?>
<section class="card">
  <h1>New Hope School</h1>
  <h2>Quiénes somos<br><small style="font-size:14px;color:var(--muted);font-weight:600">Colegio Bilingüe New Hope</small></h2>
  <p class="lead">Fundada en 1993, Nueva Esperanza comenzó como el sueño de una educadora por ofrecer educación
    bilingüe de alta calidad en Heredia. Con apenas 105 estudiantes y 17 educadores, inició sus operaciones en San
    Juan de Santa Bárbara. Hoy, tras 31 años, nos destacamos como la mejor institución de Heredia y una de las más
    reconocidas del este del país.

    Nuestra dedicación nos llevó a convertirnos en la primera Ecoescuela de Costa Rica, obteniendo la prestigiosa
    Bandera Verde y reafirmando nuestro compromiso con el medio ambiente a través del programa de Ecoescuelas. A
    lo largo de estas tres décadas, hemos formado generaciones de líderes preparados para enfrentar los desafíos
    del mundo actual, dejando huella en diversas áreas.

    Agradecemos a nuestra fundadora, docentes, personal administrativo, estudiantes y familias por ser parte de
    esta historia. Nueva Esperanza sigue comprometida con brindar una educación innovadora, sostenible y de
    calidad, impulsando a nuestros estudiantes a ser ciudadanos responsables con la sociedad y el medio ambiente.

    ¡Nueva Esperanza, un lugar para mentes sin límites!</p>
  <div>
    <div class="card" style="background:transparent;padding:14px">
      <strong>MISIÓN</strong>
      <div style="color:var(--muted);font-size:13px;margin-top:6px">Brindar a la sociedad global ciudadanos con
        sensibilidad humana, mediante una formación educativa integral, a través de un enfoque humanista y socio
        constructivista, potenciando habilidades y competencias que le permitan asumir de manera
        interdisciplinaria y responsable los retos del siglo XXI.</div>
    </div>
    <div class="card" style="background:transparent;padding:14px">
      <strong>VISIÓN</strong>
      <div style="color:var(--muted);font-size:13px;margin-top:6px">Nueva Esperanza se visualiza como una
        institución líder, pionera e innovadora en metodologías de enseñanza y tecnologías aplicadas para el
        desarrollo sostenible, en la formación de seres humanos con valores potenciando las competencias para su
        integración a la sociedad como gestores de cambio.</div>
    </div>
  </div>
  <a class="btn" href="<?= $base_url ?>/login.php">Iniciar sesión</a>
  <a class="btn" href="<?= $base_url ?>/register.php">Registrarse</a>
</section>
<?php include __DIR__ . '/components/footer.php'; ?>
