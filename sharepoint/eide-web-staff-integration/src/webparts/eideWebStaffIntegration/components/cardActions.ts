/** Native controls for cards inside Shadow DOM, without host Bootstrap/jQuery. */
export function bindCardActions(root: ShadowRoot, listingUrl: string): () => void {
  let dialog: HTMLDialogElement | undefined;
  let opener: HTMLElement | undefined;
  const close = (): void => {
    if (dialog) { if (dialog.open) dialog.close(); dialog.remove(); dialog = undefined; }
    if (opener) opener.focus();
  };
  // Edit suggestions are a separate WordPress form workflow, not a read API operation.
  root.querySelectorAll<HTMLButtonElement>('.ve-suggest-edit-btn').forEach(button => {
    const link = document.createElement('a');
    link.className = button.className;
    link.textContent = 'Suggest edit on staff site';
    const url = new URL(listingUrl);
    url.searchParams.delete('type'); url.searchParams.delete('embed_version');
    link.href = url.toString(); link.target = '_blank'; link.rel = 'noopener noreferrer';
    button.replaceWith(link);
  });
  const click = (event: Event): void => {
    const element = event.target as Element;
    if (!element.closest) return;
    const button = element.closest<HTMLElement>('[data-bs-toggle], [data-toggle], [data-bs-dismiss]');
    if (!button) return;
    if (button.getAttribute('data-bs-dismiss') === 'vemodal') { close(); return; }
    const kind = button.getAttribute('data-bs-toggle') || button.getAttribute('data-toggle');
    const targetId = button.getAttribute('data-bs-target') || button.getAttribute('data-target') || '';
    if (!/^#[\w-]+$/.test(targetId)) return;
    const target = root.getElementById(targetId.slice(1));
    if (!target) return;
    if (kind === 'collapse') {
      event.preventDefault();
      button.setAttribute('aria-expanded', String(target.classList.toggle('veshow')));
    } else if (kind === 'vemodal' && target.id.indexOf('biomodal') === 0) {
      event.preventDefault(); close(); opener = button;
      dialog = document.createElement('dialog');
      dialog.setAttribute('aria-label', target.querySelector('.vemodal-title')?.textContent || 'Staff biography');
      dialog.style.cssText = 'border:0;border-radius:10px;padding:0;width:min(800px,90vw);max-height:85vh;overflow:auto';
      const content = target.querySelector('.vemodal-content');
      if (content) dialog.appendChild(content.cloneNode(true));
      dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
      root.querySelector('.ve-staff-embed')?.appendChild(dialog);
      dialog.showModal();
    }
  };
  root.addEventListener('click', click);
  return () => { root.removeEventListener('click', click); close(); };
}
