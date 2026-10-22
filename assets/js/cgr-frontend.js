document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Create global Popup Modal container once if it doesn't exist
    if (!document.getElementById('cgr-review-modal')) {
        const modalHTML = `
            <div id="cgr-review-modal" class="cgr-modal-overlay">
                <div class="cgr-modal-content" id="cgr-modal-box">
                    <button type="button" class="cgr-modal-close" id="cgr-modal-close-btn" aria-label="Close">&times;</button>
                    <div class="cgr-modal-author-profile" style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                        <img id="cgr-modal-avatar" src="" class="cgr-avatar-img" style="display:none;" alt="">
                        <div>
                            <h4 id="cgr-modal-name" style="margin:0; color:#fff; font-size:17px; font-weight:700;"></h4>
                            <div id="cgr-modal-date" style="font-size:12px; color:#fff; opacity:0.65; margin-top:2px;"></div>
                        </div>
                    </div>
                    <div id="cgr-modal-stars" style="color:#F2B01E; font-size:20px; margin-bottom:12px;"></div>
                    <p id="cgr-modal-text" class="cgr-modal-quote"></p>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    const modal = document.getElementById('cgr-review-modal');
    const modalClose = modal.querySelector('.cgr-modal-close');

    function closeModal() {
        modal.classList.remove('cgr-modal-active');
        if (typeof window.cgrSwiperInstance !== 'undefined' && window.cgrSwiperInstance.autoplay) {
            window.cgrSwiperInstance.autoplay.start();
        }
    }

    modalClose.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // 2. Read More Toggle Logic (Modal, Scrollable Box, or Inline Expand)
    document.querySelectorAll('.cgr-read-more-trigger').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const container = this.closest('.cgr-pro-container');
            const action = container ? (container.getAttribute('data-readmore-action') || 'modal') : 'modal';
            const targetId = this.getAttribute('data-target');
            const targetEl = document.getElementById(targetId);
            if (!targetEl) return;

            const fullText = targetEl.getAttribute('data-full-text');
            const shortText = targetEl.getAttribute('data-short-text');

            if (action === 'modal') {
                const card = this.closest('.cgr-card-node');
                const authorName = card.getAttribute('data-author') || '';
                const reviewDate = card.getAttribute('data-date') || '';
                const avatarSrc  = card.getAttribute('data-avatar') || '';
                const starsEl    = card.querySelector('.cgr-stars');

                // Apply custom modal styling from container data attributes
                const modalBg     = container ? (container.getAttribute('data-modal-bg') || '#112837') : '#112837';
                const modalText   = container ? (container.getAttribute('data-modal-text') || '#ffffff') : '#ffffff';
                const modalRadius = container ? (container.getAttribute('data-modal-radius') || '12px') : '12px';

                const modalBox      = document.getElementById('cgr-modal-box');
                const modalNameEl   = document.getElementById('cgr-modal-name');
                const modalDateEl   = document.getElementById('cgr-modal-date');
                const modalTextEl   = document.getElementById('cgr-modal-text');
                const modalCloseBtn = document.getElementById('cgr-modal-close-btn');

                if (modalBox) {
                    modalBox.style.backgroundColor = modalBg;
                    modalBox.style.color = modalText;
                    modalBox.style.borderRadius = modalRadius;
                }
                if (modalNameEl) modalNameEl.style.color = modalText;
                if (modalDateEl) modalDateEl.style.color = modalText;
                if (modalTextEl) modalTextEl.style.color = modalText;
                if (modalCloseBtn) modalCloseBtn.style.color = modalText;

                modalNameEl.textContent = authorName;
                modalDateEl.textContent = reviewDate;
                modalTextEl.textContent = fullText;
                
                const modalStars = document.getElementById('cgr-modal-stars');
                if (starsEl) {
                    modalStars.innerHTML = starsEl.innerHTML;
                    const computedStarColor = window.getComputedStyle(starsEl).color;
                    if (computedStarColor) modalStars.style.color = computedStarColor;
                }

                const modalAvatar = document.getElementById('cgr-modal-avatar');
                if (avatarSrc) {
                    modalAvatar.src = avatarSrc;
                    modalAvatar.style.display = 'block';
                } else {
                    modalAvatar.style.display = 'none';
                }

                modal.classList.add('cgr-modal-active');
                if (typeof window.cgrSwiperInstance !== 'undefined' && window.cgrSwiperInstance.autoplay) {
                    window.cgrSwiperInstance.autoplay.stop();
                }
            } 
            else if (action === 'scroll') {
                if (targetEl.classList.contains('cgr-scrollable-box')) {
                    targetEl.classList.remove('cgr-scrollable-box');
                    targetEl.textContent = shortText;
                    this.textContent = this.getAttribute('data-more');
                } else {
                    targetEl.classList.add('cgr-scrollable-box');
                    targetEl.textContent = fullText;
                    this.textContent = this.getAttribute('data-less');
                }
            } 
            else {
                // Default Inline Expand
                if (targetEl.classList.contains('cgr-expanded')) {
                    targetEl.classList.remove('cgr-expanded');
                    targetEl.textContent = shortText;
                    this.textContent = this.getAttribute('data-more');
                } else {
                    targetEl.classList.add('cgr-expanded');
                    targetEl.textContent = fullText;
                    this.textContent = this.getAttribute('data-less');
                }
                
                if (typeof window.cgrSwiperInstance !== 'undefined') {
                    window.cgrSwiperInstance.updateAutoHeight(200);
                }
            }
        });
    });

    // 3. Swiper Initialization
    document.querySelectorAll('.cgr-swiper-container').forEach(function(container) {
        
        let layout = container.getAttribute('data-layout');
        let speed = parseInt(container.getAttribute('data-speed')) || 6000;
        let navType = container.getAttribute('data-nav');

        let slidesView1024 = layout === 'card_slider' ? 3 : 1;
        let slidesView768  = layout === 'card_slider' ? 2 : 1;
        
        let swiperConfig = {
            loop: true,
            autoplay: { delay: speed, disableOnInteraction: false },
            autoHeight: true,
            slidesPerView: slidesView1024,
            spaceBetween: 30,
            breakpoints: { 
                320: { slidesPerView: 1 }, 
                768: { slidesPerView: slidesView768 }, 
                1024: { slidesPerView: slidesView1024 } 
            }
        };

        if (navType === 'dots' || navType === 'both') {
            swiperConfig.pagination = { 
                el: container.querySelector('.swiper-pagination'), 
                clickable: true 
            };
        }
        
        if (navType === 'arrows' || navType === 'both') {
            let wrapper = container.closest('.cgr-slider-wrapper');
            if (wrapper) {
                swiperConfig.navigation = { 
                    nextEl: wrapper.querySelector('.swiper-button-next'), 
                    prevEl: wrapper.querySelector('.swiper-button-prev') 
                };
            }
        }

        setTimeout(function() {
            window.cgrSwiperInstance = new Swiper(container, swiperConfig);
        }, 50);
    });
});