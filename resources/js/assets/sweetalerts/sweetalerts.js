import Swal from 'sweetalert2';

const iconMap = (type) => {
  switch (type) {
    case 'success': return 'success';
    case 'warning': return 'warning';
    case 'error':   return 'error';
    case 'info':
    default:        return 'info';
  }
};

// Toast (oben rechts)
window.addEventListener('swal:toast', (e) => {
  const d = e.detail || {};
  const type = d.type || 'info';
  const title = d.title ?? ({
    success: 'Erfolg!',
    warning: 'Warnung!',
    error:   'Fehler!',
    info:    'Hinweis!',
  }[type] || 'Hinweis!');

  // Wenn redirectTo gesetzt ist, zeigen wir einen OK-Button (kein Auto-Close).
  const showConfirm = d.showConfirm ?? !!d.redirectTo;

  Swal.fire({
    toast: true,
    position: d.position || 'top-end',
    icon: iconMap(type),
    title,
    text:  d.text  ?? undefined,
    html:  d.html  ?? undefined,     // erlaubt HTML
    timer: showConfirm ? undefined : (d.timer ?? 4000),
    timerProgressBar: !showConfirm,
    showConfirmButton: showConfirm,
    confirmButtonText: d.confirmText || 'OK',
  }).then((result) => {
    // Weiterleitung: bei OK oder (falls kein Button) nach Timer-Ende
    if ((result.isConfirmed || result.dismiss === Swal.DismissReason.timer) && d.redirectTo) {
      window.location.assign(d.redirectTo);
    }
  });
});

// Modal (zentriert, mit Confirm/Cancel)
window.addEventListener('swal:alert', async (e) => {
  const d = e.detail || {};
  const type = d.type || 'info';

  const res = await Swal.fire({
    icon: iconMap(type),
    title: d.title || 'Hinweis',
    text:  d.text  ?? undefined,
    html:  d.html  ?? undefined,
    confirmButtonText: d.confirmText || 'OK',
    showCancelButton:  !!d.showCancel,
    cancelButtonText:  d.cancelText || 'Abbrechen',
    allowOutsideClick: d.allowOutsideClick ?? true,
  });

  // optional: Callback/Followup-Event
  if (d.onConfirm && res.isConfirmed) {
    window.dispatchEvent(
      new CustomEvent(d.onConfirm.name || 'swal:confirmed', { detail: d.onConfirm.detail || {} })
    );
  }

  // Redirect-Handling
  const redirectOn = d.redirectOn || 'confirm'; // 'confirm' | 'close'
  const shouldRedirect =
    d.redirectTo &&
    (
      (redirectOn === 'confirm' && res.isConfirmed) ||
      (redirectOn === 'close'   && (res.isDismissed || res.isDenied))
    );

  if (shouldRedirect) {
    window.location.assign(d.redirectTo);
  }
});
