function alta(){
    // open modal rather than popup
    const overlay = document.getElementById('modal-overlay');
    const iframe = document.getElementById('altaIframe');
    if (!overlay || !iframe) {
        // fallback: open new window
        window.open('Altausuario.html', '_blank');
        return;
    }
    iframe.src = 'Altausuario.html';
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // close button handler
    const closeBtn = document.getElementById('modal-close');
    const closeModal = () => {
        overlay.style.display = 'none';
        iframe.src = '';
        document.body.style.overflow = '';
    };
    closeBtn.onclick = closeModal;

    // listen for messages from iframe (e.g., user-created)
    window.addEventListener('message', function onMessage(e){
        try {
            const msg = e.data;
            if (msg && msg.type === 'user-created') {
                // close modal and optionally call buscar()
                closeModal();
                if (typeof window.buscar === 'function') window.buscar();
                window.removeEventListener('message', onMessage);
            }
        } catch (err) {
            // ignore
        }
    });

}