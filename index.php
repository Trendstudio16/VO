<?php
require_once 'config.php';
$products = load_products();
$settings = load_settings();

// Inicializa carrito y limpia items obsoletos
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
foreach (array_keys($_SESSION['cart']) as $cid) {
    $keep = false;
    foreach ($products as $p) if ($p['id']==$cid) { $keep = true; break; }
    if (!$keep) unset($_SESSION['cart'][$cid]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Agregar al carrito
    if (isset($_POST['add_id'])) {
        $id  = (int)$_POST['add_id'];
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        foreach ($products as $p) {
            if ($p['id']==$id && $p['stock']>0) {
                $_SESSION['cart'][$id] = min(($_SESSION['cart'][$id] ?? 0) + $qty, $p['stock']);
                break;
            }
        }
    }
    // Quitar 1 unidad
    if (isset($_POST['decrease_id'])) {
        $id = (int)$_POST['decrease_id'];
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]--;
            if ($_SESSION['cart'][$id] < 1) unset($_SESSION['cart'][$id]);
        }
    }
    // Checkout
    if (isset($_POST['checkout'])) {
        $items    = [];
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $id=>$qty) {
            foreach ($products as $p) if ($p['id']==$id) {
                $unit = $p['unit'] ?? 'UNIDAD';
                $line = $p['price'] * $qty;
                $items[]  = "- {$p['name']} x{$qty} {$unit} = $" . number_format($line,2);
                $subtotal += $line;
                break;
            }
        }
        // Actualiza stock
        foreach ($_SESSION['cart'] as $id=>$qty) {
            foreach ($products as &$p) if ($p['id']==$id) {
                $p['stock'] -= $qty; break;
            }
            unset($p);
        }
        save_products($products);

        // Arma mensaje de WhatsApp
        $msg  = "*Pedido Verdulería*\n";
        $msg .= "Cliente: ".strip_tags($_POST['fullName'])."\n";
        $msg .= "Teléfono: ".strip_tags($_POST['phone'])."\n";

        $total     = $subtotal;
        $threshold = $settings['free_shipping_threshold'];
        $fee       = $settings['shipping_fee'];

        if ($_POST['isDelivery']==='1') {
            $msg .= "Envío a: ".strip_tags($_POST['address']).", ".strip_tags($_POST['locality'])."\n";
            if (!empty($_POST['delivery_date'])) {
                $msg .= "Fecha de entrega: ".strip_tags($_POST['delivery_date'])."\n";
            }
            $from = strip_tags($_POST['time_from'] ?? '');
            $to   = strip_tags($_POST['time_to']   ?? '');
            if ($from||$to) {
                $msg .= "Horario de entrega: {$from} - {$to}\n";
            }
            if ($threshold>0 && $subtotal >= $threshold) {
                $items[] = "- Envío a domicilio (GRATIS)";
            } else {
                $items[] = "- Envío a domicilio = \$".number_format($fee,2);
                $total  += $fee;
            }
        } else {
            $msg .= "Retiro en local\n";
        }

        $msg .= "-------------------\n".implode("\n",$items)."\n";
        $msg .= "-------------------\nTotal: \$".number_format($total,2)."\n";
        if (!empty($_POST['comments'])) {
            $msg .= "Comentarios: ".strip_tags($_POST['comments']);
        }

        $_SESSION['cart'] = [];
        header('Location: https://wa.me/'.WHATSAPP_NUMBER.'?text='.urlencode($msg));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Verdulería Online</title>
  <link rel="stylesheet" href="style.css?v=6"">
</head>
<body>
  <header>
    <div class="container header-container">
      <a href="index.php"><img src="logo.png" class="logo" alt="Verdulería Logo"></a>
      <nav class="main-nav">
        <a href="#products">Productos</a>
        <a href="#cart-items">Carrito (<?php echo array_sum($_SESSION['cart']); ?>)</a>
        <a href="admin.php">Admin</a>
      </nav>
    </div>
  </header>

  <main class="container main-content">
    <!-- Nuestros Productos -->
    <section id="products" class="section products-section">
      <h2 class="section-title">Nuestros Productos</h2>
      <div class="products-grid">
        <?php foreach($products as $p): ?>
        <form method="post" class="product-card">
          <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="">
          <div class="product-info">
            <h3><?php echo htmlspecialchars($p['name']); ?></h3>
            <p class="stock">Quedan <?php echo $p['stock'].' '.($p['unit']??'UNIDAD'); ?></p>
            <p class="desc"><?php echo htmlspecialchars($p['description']); ?></p>
            <div class="price">$<?php echo number_format($p['price'],2); ?></div>
            <div class="add-row">
              <input type="number" name="qty" value="1" min="1" max="<?php echo $p['stock']; ?>">
              <input type="hidden" name="add_id" value="<?php echo $p['id']; ?>">
              <button class="btn btn-primary" type="submit"<?php echo $p['stock']<1?' disabled':''; ?>>
                Agregar
              </button>
            </div>
          </div>
        </form>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Tu Carrito -->
    <section id="cart-items" class="section cart-section">
      <h2 class="section-title">Tu Carrito</h2>
      <div class="cart-items-grid">
        <?php if (!empty($_SESSION['cart'])): ?>
          <?php foreach($_SESSION['cart'] as $id=>$qty):
            foreach($products as $p) if($p['id']==$id){
              $unit=$p['unit']??'UNIDAD';
              $line=$p['price']*$qty;
              break;
            }
          ?>
          <div class="cart-item-card">
            <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="">
            <div class="cart-item-info">
              <h4><?php echo htmlspecialchars($p['name']); ?></h4>
              <p><?php echo $qty.' '.$unit; ?></p>
              <p>$<?php echo number_format($line,2); ?></p>
              <form method="post" class="decrease-form">
                <input type="hidden" name="decrease_id" value="<?php echo $id; ?>">
                <button class="btn btn-secondary" formnovalidate>–1</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="empty-cart">Tu carrito está vacío.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- Resumen y Checkout -->
    <div class="summary-checkout-container">
      <!-- Resumen de Compra -->
      <section class="section summary-section">
        <h2 class="section-title">Resumen de Compra</h2>
        <div class="summary-box">
          <?php
            $subtotal = 0;
            foreach($_SESSION['cart'] as $id=>$qty){
              foreach($products as $p) if($p['id']==$id){
                $subtotal += $p['price']*$qty; break;
              }
            }
          ?>
          <div class="summary-row"><span>Subtotal:</span>
            <span id="subtotal" data-value="<?php echo $subtotal; ?>">
              $<?php echo number_format($subtotal,2); ?>
            </span>
          </div>
          <div id="shipping-item" class="summary-row shipping-row" style="display:none;">
            <span id="shipping-title">Envío a domicilio</span>
            <span id="shipping-cost">$<?php echo number_format($settings['shipping_fee'],2); ?></span>
          </div>
          <div class="summary-row total-row"><span>Total:</span>
            <span id="total">$<?php echo number_format($subtotal,2); ?></span>
          </div>
        </div>
      </section>

      <!-- Formulario Checkout -->
      <section class="section checkout-section">
        <h2 class="section-title">Datos de Entrega</h2>
        <form method="post" class="checkout-form">
          <div class="form-group shipping-toggle">
            <label>Envío a domicilio:</label>
            <div class="toggle-buttons">
              <label><input type="radio" name="isDelivery" value="0" required> No</label>
              <label><input type="radio" name="isDelivery" value="1"> Sí</label>
            </div>
          </div>
          <div class="form-group"><input name="address" placeholder="Dirección"></div>
          <div class="form-group"><input name="locality" placeholder="Localidad"></div>
          <div class="form-group">
            <label>Fecha de entrega (opcional)</label>
            <input type="date" name="delivery_date">
          </div>
          <div class="form-group">
            <label>Horario de entrega (opcional)</label>
            <div class="time-range">
              <input type="time" name="time_from"><span>—</span><input type="time" name="time_to">
            </div>
          </div>
          <div class="form-group"><input name="fullName" placeholder="Nombre completo" required></div>
          <div class="form-group"><input name="phone" placeholder="Teléfono" required></div>
          <div class="form-group">
            <label>Comentarios (opcional)</label>
            <textarea name="comments" placeholder="Mensaje para tu pedido"></textarea>
          </div>
          <button class="btn btn-primary checkout-button" name="checkout" value="1">
            Realizar Pedido
          </button>
        </form>
      </section>
    </div>
  </main>

  <footer class="footer">&copy; <?php echo date('Y'); ?> Verdulería Online</footer>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const fee       = <?php echo json_encode($settings['shipping_fee']); ?>;
    const threshold = <?php echo json_encode($settings['free_shipping_threshold']); ?>;
    const shipCard  = document.getElementById('shipping-item');
    const shipTitle = document.getElementById('shipping-title');
    const shipCost  = document.getElementById('shipping-cost');
    const subtotalEl= document.getElementById('subtotal');
    const totalEl   = document.getElementById('total');
    const base      = parseFloat(subtotalEl.dataset.value);

    function updateTotal(isDeliv) {
      if (isDeliv) {
        shipCard.style.display = 'flex';
        if (threshold>0 && base>=threshold) {
          shipTitle.textContent = 'Envío a domicilio (GRATIS)';
          shipCost.textContent  = '';
          totalEl.textContent   = '$' + base.toFixed(2);
        } else {
          shipTitle.textContent = 'Envío a domicilio';
          shipCost.textContent  = '$' + fee.toFixed(2);
          totalEl.textContent   = '$' + (base + fee).toFixed(2);
        }
      } else {
        shipCard.style.display = 'none';
        totalEl.textContent    = '$' + base.toFixed(2);
      }
    }

    document.querySelectorAll('input[name="isDelivery"]').forEach(radio => {
      radio.addEventListener('change', () => updateTotal(radio.value==='1'));
    });
    updateTotal(false);
  });
  </script>
</body>
</html>
