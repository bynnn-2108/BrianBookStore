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
    // Mengambil elemen pembayaran untuk mengatur tampilan transfer dan COD secara dinamis.
    const transferDetails = paymentForm.querySelector('[data-transfer-details]');
    const proofInput = paymentForm.querySelector('[data-payment-proof]');
    const fileName = paymentForm.querySelector('[data-upload-file-name]');
    // Mengambil panel uang tunai agar hanya aktif ketika metode yang dipilih adalah COD.
    const cashDetails = paymentForm.querySelector('[data-cash-payment-details]');
    const cashInput = paymentForm.querySelector('[data-cash-paid]');
    const changePreview = paymentForm.querySelector('[data-payment-change-preview]');
    const methodInputs = paymentForm.querySelectorAll('[name="metode_pembayaran"]');
    // Total dari server menjadi dasar estimasi kembalian pada sisi browser.
    const checkoutTotal = Number.parseFloat(paymentForm.dataset.orderTotal || '0');

    // Memformat nominal menjadi format Rupiah untuk umpan balik yang mudah dibaca.
    const formatCurrency = amount => new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0,
    }).format(amount);

    // Menghitung preview kembalian tanpa menggantikan validasi utama di PHP.
    const updateCashPreview = () => {
      if (!cashInput || !changePreview) {
        return;
      }

      const cashPaid = Number.parseFloat(cashInput.value);

      if (!Number.isFinite(cashPaid) || cashInput.value === '') {
        changePreview.textContent = 'Isi uang dibayar untuk melihat estimasi kembalian.';
        changePreview.classList.remove('is-valid', 'is-invalid');
        return;
      }

      const difference = cashPaid - checkoutTotal;

      if (difference >= 0) {
        changePreview.textContent = `Estimasi kembalian: ${formatCurrency(difference)}`;
        changePreview.classList.add('is-valid');
        changePreview.classList.remove('is-invalid');
        return;
      }

      changePreview.textContent = `Uang masih kurang: ${formatCurrency(Math.abs(difference))}`;
      changePreview.classList.add('is-invalid');
      changePreview.classList.remove('is-valid');
    };

    const updatePaymentFields = () => {
      const selectedInput = paymentForm.querySelector(
        '[name="metode_pembayaran"]:checked'
      );
      const selectedMethod = selectedInput ? selectedInput.value : '';
      const isTransfer = selectedMethod === 'TRANSFER';
      const isCod = selectedMethod === 'COD';

      if (transferDetails) {
        transferDetails.hidden = !isTransfer;
      }

      if (proofInput) {
        proofInput.disabled = !isTransfer;
        proofInput.required = isTransfer;

        if (!isTransfer) {
          proofInput.value = '';
          if (fileName) {
            fileName.textContent = '';
          }
        }
      }

      // Mengaktifkan input tunai hanya untuk COD agar transfer tidak meminta kembalian.
      if (cashDetails) {
        cashDetails.hidden = !isCod;
      }

      if (cashInput) {
        cashInput.disabled = !isCod;
        cashInput.required = isCod;

        if (!isCod) {
          cashInput.value = '';
        }
      }

      // Memperbarui teks preview setelah metode pembayaran berubah.
      updateCashPreview();
    };

    // Menyegarkan field transfer/COD setiap metode pembayaran dipilih.
    methodInputs.forEach(input => {
      input.addEventListener('change', updatePaymentFields);
    });

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

    // Menghitung ulang estimasi setiap kali nominal uang diubah.
    if (cashInput) {
      cashInput.addEventListener('input', updateCashPreview);
    }

    updatePaymentFields();
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
