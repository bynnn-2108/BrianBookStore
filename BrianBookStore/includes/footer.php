  <!-- Penutup konten utama dan footer publik berisi kontak serta brand. -->
  </div>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <a class="brand light" href="<?= url('index.php') ?>">
        <span class="brand-mark">B</span>
        Brian<span>BookStore</span>
      </a>
      <p>Temukan cerita terbaik untuk setiap perjalanan Anda.</p>
    </div>

    <div>
      <b>BrianBookStore</b>
      <p>Jl. Buku Impian No. 10<br>Indonesia · 0812-3456-7890</p>
    </div>
  </div>

  <div class="copyright">
    © <?= date('Y') ?> BrianBookStore. Semua hak dilindungi.
  </div>
</footer>

<script
  src="<?= url('assets/js/app.js?v=' . filemtime(__DIR__ . '/../assets/js/app.js')) ?>"
></script>
</body>
</html>
