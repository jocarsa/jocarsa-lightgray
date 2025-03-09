<?php
// admin.php – Panel de Administración
session_start();

// Conexión a SQLite para el panel de administración
$dbFile = '../databases/lightgray.db';
$dsn = "sqlite:$dbFile";
try {
    $db = new PDO($dsn);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error conectando a la base de datos: " . $e->getMessage());
}

// Crear tabla de usuarios si no existe
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    username TEXT UNIQUE,
    password TEXT,
    role TEXT
)");

// Crear usuario inicial (admin) si no existe
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
$stmt->execute([':username' => 'jocarsa']);
if ($stmt->fetchColumn() == 0) {
    $passwordHash = password_hash("jocarsa", PASSWORD_DEFAULT);
    $stmtIns = $db->prepare("INSERT INTO users (name, email, username, password, role) VALUES (:name, :email, :username, :password, 'admin')");
    $stmtIns->execute([
        ':name'     => 'Jose Vicente Carratala',
        ':email'    => 'info@josevicentecarratala.com',
        ':username' => 'jocarsa',
        ':password' => $passwordHash
    ]);
}

// Crear tabla de SEO si no existe
$db->exec("CREATE TABLE IF NOT EXISTS seo_settings (
    id INTEGER PRIMARY KEY,
    head_title TEXT,
    meta_description TEXT,
    meta_keywords TEXT,
    meta_author TEXT,
    web_title TEXT,
    web_subtitle TEXT
)");
// Si no hay registros en SEO, insertar valores por defecto
$stmtSeo = $db->query("SELECT COUNT(*) FROM seo_settings");
if ($stmtSeo->fetchColumn() == 0) {
    $stmtInsSeo = $db->prepare("INSERT INTO seo_settings (id, head_title, meta_description, meta_keywords, meta_author, web_title, web_subtitle) VALUES (1, 'Courses LMS', 'Default description', 'Default,Keywords', 'Default Author', 'Courses LMS', 'Learning made easy')");
    $stmtInsSeo->execute();
}

// Funciones de autenticación para el admin
function isAdminLoggedIn() {
    return isset($_SESSION['admin_user']) && $_SESSION['admin_user']['role'] === 'admin';
}

function redirect($page) {
    header("Location: admin.php?page=$page");
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Login
    if ($page === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username && $password) {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['admin_user'] = $user;
                redirect('dashboard');
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        } else {
            $error = "Complete todos los campos.";
        }
    }
    // Eliminar usuario
    elseif ($page === 'delete_user') {
        if (!isAdminLoggedIn()) {
            redirect('login');
        }
        $userId = intval($_POST['user_id']);
        // Evitar eliminar al usuario actualmente logueado
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $userToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userToDelete && $userToDelete['username'] !== $_SESSION['admin_user']['username']) {
            $stmtDel = $db->prepare("DELETE FROM users WHERE id = :id");
            $stmtDel->execute([':id' => $userId]);
            $msg = "Usuario eliminado.";
        } else {
            $msg = "No se puede eliminar este usuario.";
        }
        redirect('dashboard');
    }
    // Guardar ajustes SEO
    elseif ($page === 'seo') {
        if (!isAdminLoggedIn()) {
            redirect('login');
        }
        $head_title       = trim($_POST['head_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_keywords    = trim($_POST['meta_keywords'] ?? '');
        $meta_author      = trim($_POST['meta_author'] ?? '');
        $web_title        = trim($_POST['web_title'] ?? '');
        $web_subtitle     = trim($_POST['web_subtitle'] ?? '');
        $stmtSeoUpd = $db->prepare("UPDATE seo_settings SET head_title = :head_title, meta_description = :meta_description, meta_keywords = :meta_keywords, meta_author = :meta_author, web_title = :web_title, web_subtitle = :web_subtitle WHERE id = 1");
        if ($stmtSeoUpd->execute([
            ':head_title'       => $head_title,
            ':meta_description' => $meta_description,
            ':meta_keywords'    => $meta_keywords,
            ':meta_author'      => $meta_author,
            ':web_title'        => $web_title,
            ':web_subtitle'     => $web_subtitle
        ])) {
            $msg = "Ajustes SEO actualizados.";
        } else {
            $msg = "Error actualizando los ajustes.";
        }
    }
}

// Procesar logout
if ($page === 'logout') {
    session_destroy();
    redirect('login');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-wrapper">
    <header>
        <h1>Panel de Administración</h1>
        <?php if (isAdminLoggedIn()): ?>
            <div class="header-right">
                <span>Bienvenido, <?= htmlspecialchars($_SESSION['admin_user']['username']) ?></span>
                <a href="admin.php?page=logout">Logout</a>
            </div>
        <?php endif; ?>
    </header>
    <?php if (isAdminLoggedIn()): ?>
    <div class="admin-container">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin.php?page=dashboard">Dashboard</a></li>
                <li><a href="admin.php?page=seo">SEO</a></li>
            </ul>
        </nav>
        <section class="admin-content">
    <?php if ($page === 'dashboard'): ?>
        <h2>Dashboard</h2>
        <?php if (!empty($msg)) echo '<p class="msg">'.htmlspecialchars($msg).'</p>'; ?>
        <h3>Usuarios</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Acción</th>
            </tr>
            <?php
            $stmt = $db->query("SELECT * FROM users");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
            ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td>
                    <?php if ($row['username'] !== $_SESSION['admin_user']['username']): ?>
                        <form method="POST" action="admin.php?page=delete_user" onsubmit="return confirm('¿Eliminar usuario?');">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['id']) ?>">
                            <button type="submit">Eliminar</button>
                        </form>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        <h3>Cursos y Videos (Solo lectura)</h3>
        <?php 
            $jsonFile = 'playlists_data.json';
            if (file_exists($jsonFile)) {
                $courses = json_decode(file_get_contents($jsonFile), true);
                if (empty($courses)) {
                    echo "<p>No hay cursos disponibles.</p>";
                } else {
                    foreach ($courses as $courseName => $videos) {
                        echo "<h4>" . htmlspecialchars($courseName) . "</h4>";
                        echo "<ul>";
                        foreach ($videos as $video) {
                            echo "<li>" . htmlspecialchars($video['video_title']) . "</li>";
                        }
                        echo "</ul>";
                    }
                }
            } else {
                echo "<p>No se encontró el archivo JSON.</p>";
            }
        ?>
    <?php elseif ($page === 'seo'): ?>
        <h2>Ajustes SEO</h2>
        <?php if (!empty($msg)) echo '<p class="msg">'.htmlspecialchars($msg).'</p>'; ?>
        <?php 
            $stmtSeoGet = $db->query("SELECT * FROM seo_settings LIMIT 1");
            $seoSettings = $stmtSeoGet->fetch(PDO::FETCH_ASSOC);
        ?>
        <form method="POST" action="admin.php?page=seo">
            <label for="head_title">Head Title:</label><br>
            <input type="text" id="head_title" name="head_title" value="<?= htmlspecialchars($seoSettings['head_title'] ?? '') ?>"><br><br>
            
            <label for="meta_description">Meta Description:</label><br>
            <textarea id="meta_description" name="meta_description" rows="3"><?= htmlspecialchars($seoSettings['meta_description'] ?? '') ?></textarea><br><br>
            
            <label for="meta_keywords">Meta Keywords:</label><br>
            <input type="text" id="meta_keywords" name="meta_keywords" value="<?= htmlspecialchars($seoSettings['meta_keywords'] ?? '') ?>"><br><br>
            
            <label for="meta_author">Meta Author:</label><br>
            <input type="text" id="meta_author" name="meta_author" value="<?= htmlspecialchars($seoSettings['meta_author'] ?? '') ?>"><br><br>
            
            <label for="web_title">Web Title:</label><br>
            <input type="text" id="web_title" name="web_title" value="<?= htmlspecialchars($seoSettings['web_title'] ?? '') ?>"><br><br>
            
            <label for="web_subtitle">Web Subtitle:</label><br>
            <input type="text" id="web_subtitle" name="web_subtitle" value="<?= htmlspecialchars($seoSettings['web_subtitle'] ?? '') ?>"><br><br>
            
            <button type="submit">Guardar Ajustes SEO</button>
        </form>
    <?php endif; ?>
</section>

    </div>
    <?php else: ?>
    <!-- Si no está logueado, mostrar formulario de login -->
    <div class="admin-login">
        <h2>Iniciar Sesión</h2>
        <?php if (!empty($error)) echo '<p class="error">'.htmlspecialchars($error).'</p>'; ?>
        <form method="POST" action="admin.php?page=login">
            <label>Usuario: <input type="text" name="username"></label><br>
            <label>Contraseña: <input type="password" name="password"></label><br>
            <button type="submit">Ingresar</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<footer>
    <p>&copy; <?= date("Y") ?> Panel de Administración</p>
</footer>
</body>
</html>

