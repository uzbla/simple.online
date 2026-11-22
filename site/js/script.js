// js/script.js
document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('helloBtn');
  btn.addEventListener('click', () => {
    alert('Salom! Saytingizga xush kelibsiz.');
  });

  const form = document.getElementById('contactForm');
  const result = document.getElementById('formResult');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(form);
    const name = formData.get('name');
    // Oddiy front-end tasdiqlash / javob:
    result.textContent = `Rahmat, ${name}! Xabaringiz qabul qilindi (misol uchun).`;
    form.reset();
  });
});
