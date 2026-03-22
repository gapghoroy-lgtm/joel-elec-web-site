

(function () {
    'use strict';

    const form     = document.getElementById('devis');
    const feedback = document.getElementById('form-feedback');
    const btnSend  = document.getElementById('btn-send');

    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        hideFeedback();

        if (!validateForm()) return;

        const formData = new FormData(form);

        setLoading(true);

        try {
            const response = await fetch('api/submit-devis.php', {
                method: 'POST',
                body: formData,
            });

            const result = await response.json();

            if (result.success) {
                showFeedback(result.message, 'success');
                form.reset(); // Vider le formulaire
            } else {
                showFeedback(result.message, 'error');
            }

        } catch (error) {
            showFeedback(
                'Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.',
                'error'
            );
        } finally {
            setLoading(false);
        }
    });

    function validateForm() {
        const nom       = form.querySelector('[name="nom"]').value.trim();
        const telephone = form.querySelector('[name="telephone"]').value.trim();
        const email     = form.querySelector('[name="email"]').value.trim();
        const service   = form.querySelector('[name="service"]').value;
        const batiment  = form.querySelector('[name="type_batiment"]').value;
        const lieu      = form.querySelector('[name="localisation"]').value.trim();
        const photo     = form.querySelector('[name="photo"]').files[0];

        if (nom.length < 2) {
            showFeedback('Veuillez entrer votre nom complet.', 'error');
            return false;
        }

        if (telephone.length < 6) {
            showFeedback('Veuillez entrer un numéro de téléphone valide.', 'error');
            return false;
        }

        if (email && !isValidEmail(email)) {
            showFeedback('L\'adresse email n\'est pas valide.', 'error');
            return false;
        }

        if (!service) {
            showFeedback('Veuillez sélectionner un type de service.', 'error');
            return false;
        }

        if (!batiment) {
            showFeedback('Veuillez sélectionner un type de bâtiment.', 'error');
            return false;
        }

        if (lieu.length < 2) {
            showFeedback('Veuillez indiquer votre localisation.', 'error');
            return false;
        }

        if (photo) {
            const allowedTypes = ['image/jpeg', 'image/png'];
            const maxSize = 2 * 1024 * 1024; // 2 Mo

            if (!allowedTypes.includes(photo.type)) {
                showFeedback('Seuls les fichiers JPG et PNG sont acceptés.', 'error');
                return false;
            }

            if (photo.size > maxSize) {
                showFeedback('Le fichier ne doit pas dépasser 2 Mo.', 'error');
                return false;
            }
        }

        return true;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showFeedback(message, type) {
        feedback.textContent = message;
        feedback.className = 'form-feedback form-feedback--' + type;
        feedback.style.display = 'block';

        // Scroll vers le message
        feedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideFeedback() {
        feedback.style.display = 'none';
        feedback.textContent = '';
    }

    function setLoading(isLoading) {
        btnSend.disabled = isLoading;
        btnSend.textContent = isLoading ? 'Envoi en cours...' : 'Envoyer la demande';
    }

})();
