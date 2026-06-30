<?php
/**
 * Public subscription page — lists plans and lets a customer subscribe.
 */

declare(strict_types=1);

require_once __DIR__ . '/php/SubscriptionService.php';

$service = new SubscriptionService();
$plans = $service->plans();
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="ka">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PrintCo — გამოწერა</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a href="index.php" class="logo">Print<span>Co</span></a>
      <nav class="nav">
        <a href="index.php#services">სერვისები</a>
        <a href="index.php#calculator">კალკულატორი</a>
        <a href="subscribe.php">გამოწერა</a>
      </nav>
    </div>
  </header>

  <section class="hero">
    <div class="container hero-inner">
      <h1>ყოველთვიური გამოწერა</h1>
      <p>აირჩიეთ გეგმა — თანხა ავტომატურად ჩამოიჭრება ყოველ თვე. გაუქმება ნებისმიერ დროს.</p>
    </div>
  </section>

  <section class="services">
    <div class="container">
      <h2 class="section-title">აირჩიეთ გეგმა</h2>
      <div class="cards">
        <?php foreach ($plans as $plan): ?>
          <div class="card">
            <h3><?= htmlspecialchars($plan['name']) ?></h3>
            <p><?= htmlspecialchars($plan['description']) ?></p>
            <span class="price-from">
              <?= htmlspecialchars(SubscriptionService::formatAmount((int) $plan['amount_minor'], $plan['currency'])) ?> / თვე
            </span>

            <form method="post" action="php/subscribe.php" style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
              <input type="hidden" name="plan" value="<?= htmlspecialchars($plan['code']) ?>">
              <input type="text"  name="name"  placeholder="სახელი და გვარი" required>
              <input type="email" name="email" placeholder="ელ.ფოსტა" required>
              <button type="submit" class="btn btn-primary" style="background:var(--primary);color:#fff;">
                გამოწერა — <?= htmlspecialchars(SubscriptionService::formatAmount((int) $plan['amount_minor'], $plan['currency'])) ?>/თვე
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <footer class="site-footer">
    <div class="container">
      <p>&copy; <?= $year ?> PrintCo. ყველა უფლება დაცულია.</p>
    </div>
  </footer>
</body>
</html>
