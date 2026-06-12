document.addEventListener('DOMContentLoaded', () => {
  // Mobile nav toggle
  const navToggle = document.getElementById('navToggle');
  const nav = document.querySelector('.nav');
  navToggle.addEventListener('click', () => {
    nav.classList.toggle('open');
  });

  // Price calculator
  const calcForm = document.getElementById('calcForm');
  const calcResult = document.getElementById('calcResult');

  calcForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(calcForm);

    try {
      const response = await fetch('php/calculate.php', {
        method: 'POST',
        body: formData,
      });
      const data = await response.json();

      if (data.error) {
        calcResult.innerHTML = `<p class="calc-error">${data.error}</p>`;
        return;
      }

      calcResult.innerHTML = `
        <table>
          <tr><td>პროდუქტი</td><td>${data.product}</td></tr>
          <tr><td>ერთეულის ფასი</td><td>${data.unit_price.toFixed(2)} ₾</td></tr>
          <tr><td>რაოდენობა</td><td>${data.quantity}</td></tr>
          <tr><td>ფასდაკლება</td><td>${data.discount}%</td></tr>
          <tr><td>ჯამი (ფასდაკლების გარეშე)</td><td>${data.subtotal.toFixed(2)} ₾</td></tr>
          <tr class="total-row"><td>გადასაცემი ჯამი</td><td>${data.total.toFixed(2)} ₾</td></tr>
        </table>
      `;
    } catch (err) {
      calcResult.innerHTML = `<p class="calc-error">დაფიქსირდა შეცდომა, სცადეთ თავიდან.</p>`;
    }
  });

  // Contact form
  const contactForm = document.getElementById('contactForm');
  const contactResult = document.getElementById('contactResult');

  contactForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(contactForm);

    try {
      const response = await fetch('php/contact.php', {
        method: 'POST',
        body: formData,
      });
      const data = await response.json();

      if (data.success) {
        contactResult.textContent = 'მადლობა! თქვენი შეტყობინება გაგზავნილია.';
        contactResult.className = 'contact-result success';
        contactForm.reset();
      } else {
        contactResult.textContent = data.errors.join(', ');
        contactResult.className = 'contact-result error';
      }
    } catch (err) {
      contactResult.textContent = 'დაფიქსირდა შეცდომა, სცადეთ თავიდან.';
      contactResult.className = 'contact-result error';
    }
  });
});
