(function () {
    document.addEventListener('DOMContentLoaded', () => {
        initializeAnimations();
        initializeFormInteractions();
        initializeFeatureCards();
    });

    function initializeAnimations() {
        // Mouse movement effect for light beam
        const beam = document.querySelector('.light-beam');
        if (beam) {
            document.addEventListener('mousemove', (e) => {
                const x = e.clientX / window.innerWidth;
                const y = e.clientY / window.innerHeight;
                
                const rotateX = (y - 0.5) * 30;
                const rotateY = (x - 0.5) * 30;
                
                beam.style.transform = `translate(-50%, -50%) rotate3d(${-rotateX}, ${rotateY}, 0, 12deg)`;
            });
        }

        // Initialize geometric shapes animation
        const shapes = document.querySelectorAll('.geometric-shapes .shape');
        shapes.forEach(shape => {
            shape.style.setProperty('--random-delay', `-${Math.random() * 5}s`);
        });
    }

    function initializeFormInteractions() {
        const form = document.getElementById('login-form');
        if (!form) return;

        const inputs = form.querySelectorAll('.form-input');
        const alertContainer = document.getElementById('login-alert');
        const submitBtn = document.getElementById('login-submit');
        const basePath = (window.PROVEEDORES_MVC && window.PROVEEDORES_MVC.basePath) || '';
        
        // Form validation and submission
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submitBtn) submitBtn.disabled = true;

            try {
                const formData = new FormData(form);
                const url = basePath ? basePath + '/login' : '/login';

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData,
                });

                const result = await response.json().catch(() => ({}));

                if (response.ok && result.success) {
                    showButtonSuccess();
                    setTimeout(() => {
                        const redirectTo = result.redirect || (basePath ? basePath + '/' : '/');
                        window.location.href = redirectTo;
                    }, 1000);
                    return;
                }

                const message = result.message || 'Verificar credenciales e intentar nuevamente';
                showAlert(message, 'error');
            } catch (error) {
                console.error('Login error', error);
                showAlert('Error de conexión, intente nuevamente', 'error');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        // Input animations
        inputs.forEach(input => {
            const wrapper = input.closest('.input-floating');
            if (!wrapper) return;

            input.addEventListener('focus', () => {
                wrapper.classList.add('focused');
                wrapper.style.transform = 'scale(1.02)';
            });

            input.addEventListener('blur', () => {
                wrapper.classList.remove('focused');
                wrapper.style.transform = '';
            });

            // Check initial value
            if (input.value) {
                wrapper.classList.add('has-value');
            }

            input.addEventListener('input', () => {
                wrapper.classList.toggle('has-value', input.value.length > 0);
            });
        });

        // Login button hover effect
        const loginButton = document.querySelector('.login-button');
        if (loginButton) {
            loginButton.addEventListener('mousemove', (e) => {
                const rect = loginButton.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const angleX = (y - centerY) / 15;
                const angleY = (centerX - x) / 15;
                
                loginButton.style.transform = `
                    perspective(1000px)
                    rotateX(${angleX}deg)
                    rotateY(${angleY}deg)
                    scale3d(1.02, 1.02, 1.02)
                `;
            });

            loginButton.addEventListener('mouseleave', () => {
                loginButton.style.transform = '';
            });
        }

        // Helper functions
        function showAlert(message, type = 'error') {
            const alert = document.createElement('div');
            alert.className = `alert-message ${type}`;
            alert.innerHTML = `
                <div class="alert-icon">
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                </div>
                <div class="alert-content">${message}</div>
            `;

            const existingAlert = document.querySelector('.alert-message');
            if (existingAlert) {
                existingAlert.remove();
            }

            form.insertBefore(alert, form.firstChild);
        }

        function showButtonSuccess() {
            if (!submitBtn) return;
            const icon = submitBtn.querySelector('.button-icon i');
            if (icon) {
                icon.className = 'fas fa-check';
            }
            submitBtn.classList.add('success');
        }
    }

    function initializeFeatureCards() {
        const cards = document.querySelectorAll('.feature-card');
        cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 10;
                const rotateY = (centerX - x) / 10;
                
                card.style.transform = `
                    translateY(-4px)
                    rotateX(${rotateX}deg)
                    rotateY(${rotateY}deg)
                    scale3d(1.02, 1.02, 1.02)
                `;
                
                card.style.boxShadow = `
                    0 15px 35px -5px rgba(0, 0, 0, 0.5),
                    ${-rotateY/2}px ${-rotateX/2}px 20px rgba(99, 102, 241, 0.25)
                `;
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
                card.style.boxShadow = '';
            });
        });
    }
})();