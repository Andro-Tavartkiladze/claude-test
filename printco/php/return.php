<?php
/**
 * Browser landing page after the customer returns from the gateway.
 * Purely informational — the authoritative activation happens in webhook.php.
 */

declare(strict_types=1);

$status = $_GET['status'] ?? 'unknown';
$ok = $status === 'success';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ka">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>გადახდის შედეგი</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="container" style="max-width:480px;margin:60px auto;text-align:center;">
    <div class="calc-box">
      <?php if ($ok): ?>
        <h2 class="section-title">გმადლობთ! 🎉</h2>
        <p>თქვენი გამოწერა გააქტიურდა. თანხა ავტომატურად ჩამოგეჭრებათ ყოველ თვე.</p>
        <p>დადასტურება გამოგეგზავნებათ ელ.ფოსტაზე.</p>
      <?php else: ?>
        <h2 class="section-title">გადახდა არ შესრულდა</h2>
        <p>სამწუხაროდ გადახდა ვერ დასრულდა. სცადეთ ხელახლა.</p>
      <?php endif; ?>
      <p style="margin-top:20px;"><a href="/subscribe.php" class="btn btn-primary" style="background:var(--primary);color:#fff;">გეგმებზე დაბრუნება</a></p>
    </div>
  </div>
</body>
</html>
