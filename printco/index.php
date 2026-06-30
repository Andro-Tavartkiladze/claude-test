<?php
$products = require __DIR__ . '/php/products.php';
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="ka">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PrintCo — პოლიგრაფიული მომსახურება</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <header class="site-header">
    <div class="container header-inner">
      <a href="#" class="logo">Print<span>Co</span></a>
      <nav class="nav">
        <a href="#services">სერვისები</a>
        <a href="#calculator">კალკულატორი</a>
        <a href="#gallery">გალერეა</a>
        <a href="subscribe.php">გამოწერა</a>
        <a href="#contact">კონტაქტი</a>
      </nav>
      <button class="nav-toggle" id="navToggle" aria-label="მენიუ">☰</button>
    </div>
  </header>

  <section class="hero">
    <div class="container hero-inner">
      <h1>ბეჭდვა, რომელსაც ენდობით</h1>
      <p>ბიზნეს ბარათები, ფლაერები, ბროშურები და ბანერები — სწრაფად, ხარისხიანად და ხელმისაწვდომ ფასად.</p>
      <a href="#calculator" class="btn btn-primary">გამოთვალე ფასი</a>
    </div>
  </section>

  <section id="services" class="services">
    <div class="container">
      <h2 class="section-title">ჩვენი სერვისები</h2>
      <div class="cards">
        <?php foreach ($products as $key => $p): ?>
          <div class="card">
            <h3><?= htmlspecialchars($p['label']) ?></h3>
            <p>დაბალი ფასები, მაღალი ხარისხი და სწრაფი შესრულება.</p>
            <span class="price-from">დან <?= number_format($p['base_price'], 2) ?> ₾</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="calculator" class="calculator">
    <div class="container">
      <h2 class="section-title">ფასების კალკულატორი</h2>
      <div class="calc-box">
        <form id="calcForm">
          <div class="form-row">
            <label for="product">პროდუქტი</label>
            <select id="product" name="product">
              <?php foreach ($products as $key => $p): ?>
                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($p['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <label for="paper">ქაღალდის ტიპი</label>
            <select id="paper" name="paper">
              <option value="standard">სტანდარტული</option>
              <option value="matte">მატი</option>
              <option value="glossy">გლანცი</option>
              <option value="premium">პრემიუმ</option>
            </select>
          </div>

          <div class="form-row">
            <label for="sides">ბეჭდვა</label>
            <select id="sides" name="sides">
              <option value="single">ცალმხრივი</option>
              <option value="double">ორმხრივი</option>
            </select>
          </div>

          <div class="form-row">
            <label for="quantity">რაოდენობა</label>
            <input type="number" id="quantity" name="quantity" min="1" value="100">
          </div>

          <button type="submit" class="btn btn-primary">გამოთვლა</button>
        </form>

        <div class="calc-result" id="calcResult">
          <p class="calc-placeholder">აირჩიეთ პარამეტრები და დააჭირეთ „გამოთვლას"</p>
        </div>
      </div>
    </div>
  </section>

  <section id="gallery" class="gallery">
    <div class="container">
      <h2 class="section-title">ნამუშევრები</h2>
      <div class="gallery-grid">
        <div class="gallery-item">ბიზნეს ბარათები</div>
        <div class="gallery-item">ბუკლეტები</div>
        <div class="gallery-item">ბანერები</div>
        <div class="gallery-item">პოსტერები</div>
        <div class="gallery-item">პაკეტები</div>
        <div class="gallery-item">ეტიკეტები</div>
      </div>
    </div>
  </section>

  <section id="contact" class="contact">
    <div class="container">
      <h2 class="section-title">დაგვიკავშირდით</h2>
      <form id="contactForm" class="contact-form">
        <div class="form-row">
          <label for="name">სახელი</label>
          <input type="text" id="name" name="name" required>
        </div>
        <div class="form-row">
          <label for="phone">ტელეფონი</label>
          <input type="text" id="phone" name="phone" required placeholder="+995 5xx xx xx xx">
        </div>
        <div class="form-row">
          <label for="message">შეტყობინება</label>
          <textarea id="message" name="message" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">გაგზავნა</button>
        <div class="contact-result" id="contactResult"></div>
      </form>
    </div>
  </section>

  <footer class="site-footer">
    <div class="container">
      <p>&copy; <?= $year ?> PrintCo. ყველა უფლება დაცულია.</p>
    </div>
  </footer>

  <script src="js/script.js"></script>
</body>
</html>
