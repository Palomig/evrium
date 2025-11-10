// Apple-style smooth animations and interactions

document.addEventListener('DOMContentLoaded', () => {
    // Mobile menu toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', () => {
            mobileMenuToggle.classList.toggle('active');
            navLinks.classList.toggle('active');
            document.body.style.overflow = navLinks.classList.contains('active') ? 'hidden' : '';
        });
    }

    // Close mobile menu when clicking on a link
    const navLinkItems = document.querySelectorAll('.nav-links a');
    navLinkItems.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                mobileMenuToggle.classList.remove('active');
                navLinks.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // Smooth scroll for navigation links
    navLinkItems.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Scroll reveal animation
    const observerOptions = {
        threshold: 0.15,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
            }
        });
    }, observerOptions);

    // Observe feature cards
    const featureCards = document.querySelectorAll('.feature-card');
    featureCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(40px)';
        card.style.transition = 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
        observer.observe(card);
    });

    // Observe role cards
    const roleCards = document.querySelectorAll('.role-card');
    roleCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(40px)';
        card.style.transition = 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
        observer.observe(card);
    });

    // Observe API cards
    const apiCards = document.querySelectorAll('.api-card');
    apiCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateX(-40px)';
        card.style.transition = 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
        observer.observe(card);
    });

    // Parallax effect for hero section
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                const scrolled = window.pageYOffset;
                const heroContent = document.querySelector('.hero-content');
                const floatingCards = document.querySelectorAll('.floating-card');

                if (heroContent) {
                    heroContent.style.transform = `translateY(${scrolled * 0.3}px)`;
                    heroContent.style.opacity = Math.max(1 - scrolled / 500, 0);
                }

                floatingCards.forEach((card, index) => {
                    const speed = 0.1 + (index * 0.05);
                    card.style.transform = `translateY(${scrolled * speed}px)`;
                });

                ticking = false;
            });
            ticking = true;
        }
    });

    // Floating cards interactive movement
    const heroVisual = document.querySelector('.hero-visual');
    if (heroVisual) {
        document.addEventListener('mousemove', (e) => {
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;

            const cards = document.querySelectorAll('.floating-card');
            cards.forEach((card, index) => {
                const speed = (index + 1) * 10;
                const x = (mouseX - 0.5) * speed;
                const y = (mouseY - 0.5) * speed;

                card.style.transform = `translate(${x}px, ${y}px)`;
            });
        });
    }

    // Button hover effect with ripple
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                left: ${x}px;
                top: ${y}px;
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                transform: translate(-50%, -50%);
                animation: ripple 0.6s ease-out;
            `;

            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Add ripple animation to CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                width: 300px;
                height: 300px;
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    // Nav bar hide/show on scroll
    let lastScroll = 0;
    const nav = document.querySelector('.nav');

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;

        if (currentScroll <= 0) {
            nav.style.transform = 'translateY(0)';
            return;
        }

        if (currentScroll > lastScroll && currentScroll > 100) {
            // Scrolling down
            nav.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            nav.style.transform = 'translateY(0)';
        }

        lastScroll = currentScroll;
    });

    nav.style.transition = 'transform 0.3s cubic-bezier(0.16, 1, 0.3, 1)';

    // API card copy endpoint on click
    const apiEndpoints = document.querySelectorAll('.api-endpoint');
    apiEndpoints.forEach(endpoint => {
        endpoint.style.cursor = 'pointer';
        endpoint.addEventListener('click', async function() {
            const text = this.textContent;
            try {
                await navigator.clipboard.writeText(text);

                // Show feedback
                const originalText = this.textContent;
                this.textContent = '✓ Скопировано!';
                this.style.color = '#30d158';

                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.color = '';
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        });
    });

    // Animated counter for stats (if you want to add later)
    const animateCounter = (element, target, duration = 2000) => {
        let start = 0;
        const increment = target / (duration / 16);
        const timer = setInterval(() => {
            start += increment;
            if (start >= target) {
                element.textContent = Math.ceil(target);
                clearInterval(timer);
            } else {
                element.textContent = Math.ceil(start);
            }
        }, 16);
    };

    // Phone mockup message animation
    const phoneScreen = document.querySelector('.phone-screen');
    if (phoneScreen) {
        const observerPhone = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const message = entry.target.querySelector('.bot-message');
                    if (message) {
                        message.style.animation = 'none';
                        setTimeout(() => {
                            message.style.animation = 'slideIn 1s ease-out';
                        }, 100);
                    }
                }
            });
        }, { threshold: 0.5 });

        observerPhone.observe(phoneScreen);
    }

    // Gradient animation on hover for cards
    const cards = document.querySelectorAll('.feature-card, .role-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.background = `
                linear-gradient(135deg,
                    rgba(0, 113, 227, 0.05) 0%,
                    rgba(45, 45, 47, 1) 100%)
            `;
        });

        card.addEventListener('mouseleave', function() {
            if (!this.classList.contains('role-card-premium')) {
                this.style.background = 'var(--color-bg-secondary)';
            }
        });
    });

    // Tech list items animation on hover
    const techItems = document.querySelectorAll('.tech-list li');
    techItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';

        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, index * 100);
    });

    // Feature cards tilt effect on mouse move
    featureCards.forEach(card => {
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;

            this.style.transform = `
                perspective(1000px)
                rotateX(${rotateX}deg)
                rotateY(${rotateY}deg)
                translateY(-8px)
                scale3d(1.02, 1.02, 1.02)
            `;
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });

    // Smooth reveal for section titles
    const sectionTitles = document.querySelectorAll('.section-title');
    sectionTitles.forEach(title => {
        title.style.opacity = '0';
        title.style.transform = 'translateY(30px)';
        title.style.transition = 'all 1s cubic-bezier(0.16, 1, 0.3, 1)';

        const titleObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.3 });

        titleObserver.observe(title);
    });

    // Loading animation
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.5s ease';
        document.body.style.opacity = '1';
    }, 100);

    console.log('%c🎨 Evrium CRM Landing Page', 'font-size: 20px; font-weight: bold; color: #0071e3;');
    console.log('%cDesigned with Apple-style aesthetics', 'font-size: 14px; color: #a1a1a6;');
});

// Preload animations
window.addEventListener('load', () => {
    // Add loaded class to body for additional animations if needed
    document.body.classList.add('loaded');
});
