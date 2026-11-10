/* JavaScript global: mode gelap, navigasi, transisi, checkout, dan interaksi tombol. */
document.addEventListener('DOMContentLoaded', () => {
  // Menentukan key localStorage dan elemen body sebagai state global UI.
  const darkModeStorageKey = 'brianbookstore-dark-mode';
  const body = document.body;
  let savedDarkMode = false;

  try {
    savedDarkMode = localStorage.getItem(darkModeStorageKey) === 'true';
  } catch {
    savedDarkMode = false;
  }

  // Mengaktifkan tema gelap, menyimpan preferensi, dan memperbarui label aksesibilitas.
  const setDarkMode = enabled => {
    body.classList.toggle('dark-mode', enabled);

    try {
      localStorage.setItem(darkModeStorageKey, enabled ? 'true' : 'false');
    } catch {
      // Preferensi tetap aktif untuk halaman saat ini jika penyimpanan browser dibatasi.
    }

    document.querySelectorAll('[data-dark-mode-toggle]').forEach(toggle => {
      toggle.setAttribute('aria-pressed', String(enabled));
    });

    document.querySelectorAll('[data-dark-mode-label]').forEach(label => {
      label.textContent = enabled ? 'ON' : 'OFF';
    });
  };

  setDarkMode(savedDarkMode);

  // Mengatur tombol hamburger agar navigasi mobile dapat dibuka dan ditutup.
  const navToggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.main-nav');

  if (navToggle && nav) {
    navToggle.addEventListener('click', () => {
      const isOpen = nav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', String(isOpen));
    });
  }

  // Mengambil main content sebagai target animasi perpindahan halaman.
  const page = document.querySelector('.page-transition');

  if (page) {
    document.querySelectorAll('a[href]').forEach(link => {
      link.addEventListener('click', event => {
        if (
          event.defaultPrevented
          || event.button !== 0
          || event.metaKey
          || event.ctrlKey
          || event.shiftKey
          || event.altKey
          || link.target === '_blank'
          || link.hasAttribute('download')
          || link.closest('.site-header, .main-nav, .sidebar, .account-menu')
        ) {
          return;
        }

        let destination;

        try {
          destination = new URL(link.href, window.location.href);
        } catch {
          return;
        }

        if (
          destination.origin !== window.location.origin
          || destination.href === window.location.href
          || destination.pathname === window.location.pathname
             && destination.search === window.location.search
             && destination.hash !== ''
        ) {
          return;
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
          return;
        }

        event.preventDefault();
        page.classList.add('page-leaving');
        window.setTimeout(() => {
          window.location.href = destination.href;
        }, 240);
      });
    });
  }

  // Menambahkan dialog konfirmasi pada aksi berisiko seperti hapus akun.
  document.querySelectorAll('[data-confirm]').forEach(element => {
    element.addEventListener('click', event => {
      if (!confirm(element.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  // Mengirim filter admin segera setelah pilihan select berubah.
  document.querySelectorAll('[data-auto-submit]').forEach(element => {
    element.addEventListener('change', () => element.form.submit());
  });

  // Menyambungkan switch mode gelap pada seluruh halaman yang memilikinya.
  document.querySelectorAll('[data-dark-mode-toggle]').forEach(toggle => {
    toggle.addEventListener('click', () => {
      setDarkMode(!body.classList.contains('dark-mode'));
    });
  });

  // Checkout memakai data attribute agar script tetap aman di halaman lain.
  const paymentForm = document.querySelector('[data-payment-form]');

  if (paymentForm) {
    // Checkout hanya memakai transfer sehingga detail rekening selalu ditampilkan.
    const transferDetails = paymentForm.querySelector('[data-transfer-details]');
    const proofInput = paymentForm.querySelector('[data-payment-proof]');
    const fileName = paymentForm.querySelector('[data-upload-file-name]');

    if (transferDetails) {
      transferDetails.hidden = false;
    }

    if (proofInput) {
      proofInput.disabled = false;
      proofInput.required = true;
    }

    // Memvalidasi ukuran bukti pembayaran sebelum form dikirim ke server.
    if (proofInput && fileName) {
      proofInput.addEventListener('change', () => {
        const file = proofInput.files && proofInput.files[0];

        if (!file) {
          proofInput.setCustomValidity('');
          fileName.textContent = '';
          return;
        }

        if (file.size > 2 * 1024 * 1024) {
          proofInput.setCustomValidity('Ukuran bukti pembayaran maksimal 2 MB.');
          fileName.textContent = 'File terlalu besar. Pilih file maksimal 2 MB.';
          return;
        }

        proofInput.setCustomValidity('');
        fileName.textContent = `Dipilih: ${file.name}`;
      });
    }

  }

  // Menyalin nomor rekening menggunakan Clipboard API dengan fallback label.
  document.querySelectorAll('[data-copy-value]').forEach(button => {
    button.addEventListener('click', async () => {
      const originalLabel = button.textContent;

      try {
        await navigator.clipboard.writeText(button.dataset.copyValue || '');
        button.textContent = 'Tersalin';
      } catch {
        button.textContent = 'Salin manual';
      }

      window.setTimeout(() => {
        button.textContent = originalLabel;
      }, 1800);
    });
  });
});
