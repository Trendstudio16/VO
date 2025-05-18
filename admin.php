<?php
session_start();
require_once 'config.php';

// Contraseña de acceso al admin
const ADMIN_PASS = '281218';

// Si no está autenticado, mostramos el formulario de login
if (!isset($_SESSION['admin_logged_in'])) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        if ($_POST['admin_password'] === ADMIN_PASS) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Contraseña incorrecta';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Login Admin - Verdulería</title>
      <style>
        body { font-family: sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-box { background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); width: 100%; max-width: 320px; }
        .login-box h1 { margin-bottom: 16px; font-size: 1.25rem; color: #E70013; }
        .login-box input { width: 100%; padding: 8px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 4px; }
        .login-box button { width: 100%; padding: 10px; background: #E70013; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .login-box .error { color: #c00; margin-bottom: 12px; }
      </style>
    </head>
    <body>
      <div class="login-box">
        <h1>Acceso Admin</h1>
        <?php if ($error): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="password" name="admin_password" placeholder="Contraseña" required autofocus>
          <button type="submit">Ingresar</button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Si llegamos aquí, está autenticado
$products = load_products();
$settings = load_settings();

// Guardar configuración (envío y umbral)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])) {
    $settings['shipping_fee']            = (float) $_POST['shipping_fee'];
    $settings['free_shipping_threshold'] = (float) $_POST['free_shipping_threshold'];
    save_settings($settings);
    header('Location: admin.php');
    exit;
}

// Directorio de uploads
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// CRUD de productos
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']!=='save_settings') {
    $action    = $_POST['action'];
    $id        = $_POST['id'] ?? null;
    $imagePath = $_POST['existing_image'] ?? '';

    // Manejo de imagen subida
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $ext      = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $filename = 'prod_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename);
        $imagePath = 'uploads/' . $filename;
    }

    $item = [
        'id'          => $id ?: time(),
        'name'        => trim($_POST['name']),
        'description' => trim($_POST['description']),
        'price'       => (float) $_POST['price'],
        'stock'       => (int)   $_POST['stock'],
        'unit'        => $_POST['unit'],
        'image'       => $imagePath,
    ];

    if ($action === 'add') {
        $products[] = $item;
    } elseif ($action === 'edit' && $id) {
        foreach ($products as &$p) {
            if ($p['id'] == $id) { $p = $item; break; }
        }
        unset($p);
    } elseif ($action === 'delete' && $id) {
        $products = array_filter($products, fn($p) => $p['id'] != $id);
    }

    save_products(array_values($products));
    header('Location: admin.php');
    exit;
}

// Preparar para editar
$editItem = null;
if (isset($_GET['edit'])) {
    foreach ($products as $p) {
        if ($p['id'] == $_GET['edit']) {
            $editItem = $p;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Verdulería</title>
  <link rel="stylesheet" href="style.css?v=6">
</head>
<body>
<header>
  <div class="container header-container">
    <a href="index.php"><img src="logo.png" alt="Logo" class="logo"></a>
    <nav class="main-nav">
      <a href="index.php">Inicio</a>
      <a href="admin.php">Admin</a>
    </nav>
  </div>
</header>

<main class="container">
  <!-- Configuración -->
  <section class="section">
    <h1 class="section-title">Configuración</h1>
    <form method="post">
      <div class="form-group">
        <label>Cargo por envío ($)</label>
        <input type="number" step="0.01" name="shipping_fee"
               value="<?php echo htmlspecialchars($settings['shipping_fee']); ?>">
      </div>
      <div class="form-group">
        <label>Envío gratis si subtotal supera ($)</label>
        <input type="number" step="0.01" name="free_shipping_threshold"
               value="<?php echo htmlspecialchars($settings['free_shipping_threshold']); ?>">
      </div>
      <button class="btn btn-primary" name="save_settings">Guardar Configuración</button>
    </form>
  </section>

  <!-- Formulario Crear/Editar Producto -->
  <section class="section">
    <h2 class="section-title"><?php echo $editItem ? 'Editar Producto' : 'Nuevo Producto'; ?></h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="<?php echo $editItem ? 'edit' : 'add'; ?>">
      <input type="hidden" name="id"     value="<?php echo $editItem['id'] ?? ''; ?>">

      <div class="form-group">
        <label>Nombre</label>
        <input name="name" required value="<?php echo htmlspecialchars($editItem['name'] ?? ''); ?>">
      </div>
      <div class="form-group">
        <label>Descripción</label>
        <textarea name="description"><?php echo htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
      </div>
      <div class="form-group">
        <label>Precio</label>
        <input type="number" name="price" required step="0.01" value="<?php echo $editItem['price'] ?? ''; ?>">
      </div>
      <div class="form-group">
        <label>Stock</label>
        <input type="number" name="stock" required value="<?php echo $editItem['stock'] ?? ''; ?>">
      </div>
      <div class="form-group">
        <label>Unidad</label>
        <select name="unit">
          <?php foreach (['UNIDAD','KG','DOCENA'] as $u): ?>
            <option value="<?php echo $u; ?>" <?php echo (isset($editItem['unit']) && $editItem['unit']==$u) ? 'selected' : ''; ?>>
              <?php echo $u; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Imagen</label>
        <input type="file" name="image_file" accept="image/*">
        <?php if (!empty($editItem['image'])): ?>
          <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($editItem['image']); ?>">
        <?php endif; ?>
      </div>

      <div class="form-group">
        <button class="btn btn-primary"><?php echo $editItem ? 'Actualizar' : 'Crear'; ?></button>
        <?php if ($editItem): ?>
          <a class="btn btn-secondary" href="admin.php">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <!-- Lista de Productos -->
  <section class="section">
    <h2 class="section-title">Productos Registrados</h2>
    <div class="products-grid">
      <?php foreach ($products as $p): ?>
      <div class="product-card">
        <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="">
        <div class="product-info">
          <h3><?php echo htmlspecialchars($p['name']); ?> — <?php echo $p['stock'].' '.$p['unit']; ?></h3>
          <p><?php echo htmlspecialchars($p['description']); ?></p>
          <p>Precio: $<?php echo number_format($p['price'],2); ?></p>
          <div class="add-row">
            <a class="btn btn-primary" href="admin.php?edit=<?php echo $p['id']; ?>">Editar</a>
            <a class="btn btn-secondary"
               href="admin.php?action=delete&id=<?php echo $p['id']; ?>"
               onclick="return confirm('¿Eliminar este producto?')">
              Eliminar
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>

<footer class="footer">&copy; <?php echo date('Y'); ?> Verdulería Online</footer>
