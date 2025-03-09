<?php
// index.php – Front-end del LMS

// Cargar ajustes SEO desde la base de datos de administración (con valores por defecto)
$seo = [
    'head_title'       => 'Courses LMS',
    'meta_description' => 'Default description',
    'meta_keywords'    => 'Default,Keywords',
    'meta_author'      => 'Default Author',
    'web_title'        => 'Courses LMS',
    'web_subtitle'     => 'Learning made easy'
];

try {
    $dbSEO = new PDO("sqlite:../databases/lightgray.db");
    $dbSEO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Crear la tabla SEO si no existe
    $dbSEO->exec("CREATE TABLE IF NOT EXISTS seo_settings (
         id INTEGER PRIMARY KEY,
         head_title TEXT,
         meta_description TEXT,
         meta_keywords TEXT,
         meta_author TEXT,
         web_title TEXT,
         web_subtitle TEXT
    )");
    $stmtSEO = $dbSEO->query("SELECT * FROM seo_settings LIMIT 1");
    $row = $stmtSEO->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $seo = $row;
    }
} catch (Exception $e) {
    // Si hay error se usan los valores por defecto
}

// Funciones para cursos
function loadCourses() {
    $jsonFile = 'playlists_data.json';
    if (file_exists($jsonFile)) {
        return json_decode(file_get_contents($jsonFile), true);
    }
    return [];
}

function getCourse($courseName) {
    $courses = loadCourses();
    return isset($courses[$courseName]) ? $courses[$courseName] : null;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<link rel="icon" type="image/svg+xml" href="lightgray.png">
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($seo['head_title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seo['meta_description']) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($seo['meta_keywords']) ?>">
    <meta name="author" content="<?= htmlspecialchars($seo['meta_author']) ?>">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1><img src="lightgray.png"><?= htmlspecialchars($seo['web_title']) ?></h1>
    <p><?= htmlspecialchars($seo['web_subtitle']) ?></p>
    <nav>
        <a href="index.php?page=home">Inicio</a>
    </nav>
</header>
<main>
<?php
if ($page === 'home') {
    // Página de inicio: muestra cursos en cuadrícula
    $courses = loadCourses();
    if (empty($courses)) {
        echo "<p>No hay cursos disponibles.</p>";
    } else {
        echo '<div class="grid-container">';
        foreach ($courses as $courseName => $videos) {
            $firstVideo = reset($videos);
            $thumb = isset($firstVideo['thumbnail_file_path']) ? $firstVideo['thumbnail_file_path'] : 'no-image.jpg';
            echo '<div class="course-tile">';
            echo '<a href="index.php?page=course&course=' . urlencode($courseName) . '">';
            echo '<img src="' . $thumb . '" alt="' . htmlspecialchars($courseName) . '">';
            echo '<h3>' . str_replace("_"," ",htmlspecialchars($courseName)) . '</h3>';
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
    }
} elseif ($page === 'course') {
    // Página del curso: lista de videos y video actual
    $courseName = $_GET['course'] ?? '';
    $course = getCourse($courseName);
    if (!$course) {
        echo "<p>Curso no encontrado.</p>";
    } else {
        // Ordenar videos alfabéticamente por título
        usort($course, function($a, $b) {
            return strcasecmp($a['video_title'], $b['video_title']);
        });
        // Seleccionar video actual; si se pasa ?video=... se usa, sino se usa el primero
        $currentVideoUrl = $_GET['video'] ?? $course[0]['video_url'];
        $currentVideo = null;
        foreach ($course as $video) {
            if ($video['video_url'] === $currentVideoUrl) {
                $currentVideo = $video;
                break;
            }
        }
        if (!$currentVideo) {
            $currentVideo = $course[0];
        }
        ?>
        <div class="course-container">
            <div class="video-list">
                <h2>Videos</h2>
                <ul>
                <?php foreach ($course as $video): ?>
                    <li>
                        <a href="index.php?page=course&course=<?= urlencode($courseName) ?>&video=<?= urlencode($video['video_url']) ?>">
                            <?= htmlspecialchars($video['video_title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <div class="video-content">
                <h2><?= htmlspecialchars($currentVideo['video_title']) ?></h2>
                <?php
                    // Extraer el ID de YouTube de la URL
                    parse_str(parse_url($currentVideo['video_url'], PHP_URL_QUERY), $ytParams);
                    $ytId = $ytParams['v'] ?? '';
                ?>
                <?php if ($ytId): ?>
                    <iframe id="ytplayer" src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>" frameborder="0" allowfullscreen></iframe>
                <?php else: ?>
                    <p>Video no disponible.</p>
                <?php endif; ?>
                <?php
                // Si existe transcripción en JSON, se usa la variable global para que el JS la cargue.
                if (!empty($currentVideo['txt_file_path']) && file_exists($currentVideo['txt_file_path'])):
                ?>
                    <div id="transcription" class="transcription"></div>
                    <script>
                        // Definir la URL de la transcripción para el JS
                        var transcriptionUrl = "<?= $currentVideo['txt_file_path'] ?>";
                    </script>
                    <script src="transcription.js"></script>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
?>
</main>
<footer>
    <p>&copy; <?= date("Y") ?> Courses LMS</p>
</footer>
<script src="https://ghostwhite.jocarsa.com/analytics.js?user=comoprogramar.es"></script>
</body>
</html>

