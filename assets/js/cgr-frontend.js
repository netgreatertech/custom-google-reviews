(function() {
    "use strict";

    function ensureModalExists() {
        var modal = document.getElementById('cgr-review-modal');
        if (!modal) {
            var modalHTML = 
                '<div id="cgr-review-modal" class="cgr-modal-overlay">' +
                    '<div class="cgr-modal-content" id="cgr-modal-box">' +
                        '<button type="button" class="cgr-modal-close" id="cgr-modal-close-btn" aria-label="Close">&times;</button>' +
                        '<div class="cgr-modal-author-profile" style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">' +
                            '<img id="cgr-modal-avatar" src="" class="cgr-avatar-img" style="display:none; width:45px; height:45px; border-radius:50%; object-fit:cover;" alt="">' +
                            '<div>' +
                                '<h4 id="cgr-modal-name" style="margin:0; font-size:17px; font-weight:700;"></h4>' +
                                '<div id="cgr-modal-date" style="font-size:12px; opacity:0.65; margin-top:2px;"></div>' +
                            '</div>' +
                        '</div>' +
                        '<div id="cgr-modal-stars" style="color:#F2B01E; font-size:20px; margin-bottom:12px;"></div>' +
                        '<p id="cgr-modal-text" class="cgr-modal-quote" style="font-style:italic; line-height:1.7; margin:15px 0 0 0; font-size:16px;"></p>' +
                    '</div>' +
                '</div>';
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            modal = document.getElementById('cgr-review-modal');

            var modalClose = document.getElementById('cgr-modal-close-btn');
            function closeModal() {
                modal.classList.remove('cgr-modal-active');
                if (typeof window.cgrSwiperInstance !== 'undefined' && window.cgrSwiperInstance.autoplay) {
                    window.cgrSwiperInstance.autoplay.start();
                }
            }
            if (modalClose) modalClose.addEventListener('click', closeModal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });
        }
        return modal;
    }

    function handleReadMoreClick(e) {
        var btn = e.target.closest('.cgr-read-more-trigger');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        var container = btn.closest('.cgr-pro-container');
        var card = btn.closest('.cgr-card-node');
        if (!card) return;

        var clickAction = container ? (container.getAttribute('data-readmore-action') || 'modal') : 'modal';
        var isInlinePosition = btn.classList.contains('cgr-inline-trigger');
        var targetEl = card.querySelector('.cgr-quote');
        
        if (!targetEl) return;

        var fullText  = targetEl.getAttribute('data-full-text') || '';
        var shortText = targetEl.getAttribute('data-short-text') || '';

        // Action 1: Popup Modal
        if (clickAction === 'modal') {
            var modal = ensureModalExists();
            var authorName = card.getAttribute('data-author') || '';
            var reviewDate = card.getAttribute('data-date') || '';
            var avatarSrc  = card.getAttribute('data-avatar') || '';
            var starsEl    = card.querySelector('.cgr-stars');

            var modalBg     = container ? (container.getAttribute('data-modal-bg') || '#112837') : '#112837';
            var modalText   = container ? (container.getAttribute('data-modal-text') || '#ffffff') : '#ffffff';
            var modalRadius = container ? (container.getAttribute('data-modal-radius') || '12px') : '12px';

            var modalBox      = document.getElementById('cgr-modal-box');
            var modalNameEl   = document.getElementById('cgr-modal-name');
            var modalDateEl   = document.getElementById('cgr-modal-date');
            var modalTextEl   = document.getElementById('cgr-modal-text');
            var modalCloseBtn = document.getElementById('cgr-modal-close-btn');

            if (modalBox) {
                modalBox.style.backgroundColor = modalBg;
                modalBox.style.color = modalText;
                modalBox.style.borderRadius = modalRadius;
            }
            if (modalNameEl) { modalNameEl.style.color = modalText; modalNameEl.textContent = authorName; }
            if (modalDateEl) { modalDateEl.style.color = modalText; modalDateEl.textContent = reviewDate; }
            if (modalTextEl) { modalTextEl.style.color = modalText; modalTextEl.textContent = fullText; }
            if (modalCloseBtn) { modalCloseBtn.style.color = modalText; }

            var modalStars = document.getElementById('cgr-modal-stars');
            if (starsEl && modalStars) {
                modalStars.innerHTML = starsEl.innerHTML;
                var computedStarColor = window.getComputedStyle(starsEl).color;
                if (computedStarColor) modalStars.style.color = computedStarColor;
            }

            var modalAvatar = document.getElementById('cgr-modal-avatar');
            if (modalAvatar) {
                if (avatarSrc) {
                    modalAvatar.src = avatarSrc;
                    modalAvatar.style.display = 'block';
                } else {
                    modalAvatar.style.display = 'none';
                }
            }

            modal.classList.add('cgr-modal-active');
            if (typeof window.cgrSwiperInstance !== 'undefined' && window.cgrSwiperInstance.autoplay) {
                window.cgrSwiperInstance.autoplay.stop();
            }
        } 
        
        // Action 2: Scrollable Box
        else if (clickAction === 'scroll') {
            if (targetEl.classList.contains('cgr-scrollable-box')) {
                targetEl.classList.remove('cgr-scrollable-box');
                
                if (isInlinePosition) {
                    targetEl.innerHTML = '"' + shortText + ' <a class="cgr-read-more-trigger cgr-inline-trigger">Read More</a>"';
                } else {
                    targetEl.innerHTML = '"' + shortText + '..."';
                    btn.textContent = 'Read More';
                }
            } else {
                targetEl.classList.add('cgr-scrollable-box');
                
                if (isInlinePosition) {
                    targetEl.innerHTML = '"' + fullText + ' <a class="cgr-read-more-trigger cgr-inline-trigger">Read Less</a>"';
                } else {
                    targetEl.innerHTML = '"' + fullText + '"';
                    btn.textContent = 'Read Less';
                }
            }
            if (typeof window.cgrSwiperInstance !== 'undefined') window.cgrSwiperInstance.updateAutoHeight(200);
        } 
        
        // Action 3: Expand Inline Stretching
        else {
            if (targetEl.classList.contains('cgr-expanded')) {
                targetEl.classList.remove('cgr-expanded');
                
                if (isInlinePosition) {
                    targetEl.innerHTML = '"' + shortText + ' <a class="cgr-read-more-trigger cgr-inline-trigger">Read More</a>"';
                } else {
                    targetEl.innerHTML = '"' + shortText + '..."';
                    btn.textContent = 'Read More';
                }
            } else {
                targetEl.classList.add('cgr-expanded');
                
                if (isInlinePosition) {
                    targetEl.innerHTML = '"' + fullText + ' <a class="cgr-read-more-trigger cgr-inline-trigger">Read Less</a>"';
                } else {
                    targetEl.innerHTML = '"' + fullText + '"';
                    btn.textContent = 'Read Less';
                }
            }
            if (typeof window.cgrSwiperInstance !== 'undefined') window.cgrSwiperInstance.updateAutoHeight(200);
        }
    }

    function initSwiperSliders() {
        if (typeof Swiper === 'undefined') return;

        document.querySelectorAll('.cgr-swiper-container').forEach(function(container) {
            if (container.swiper) return;

            var layout  = container.getAttribute('data-layout');
            var speed   = parseInt(container.getAttribute('data-speed'), 10) || 6000;
            var navType = container.getAttribute('data-nav');

            var slidesView1024 = layout === 'card_slider' ? 3 : 1;
            var slidesView768  = layout === 'card_slider' ? 2 : 1;
            
            var swiperConfig = {
                loop: true,
                autoplay: { delay: speed, disableOnInteraction: false },
                autoHeight: true,
                slidesPerView: slidesView1024,
                spaceBetween: 30,
                preventClicks: false,
                preventClicksPropagation: false,
                touchStartPreventDefault: false,
                noSwipingSelector: '.cgr-read-more-trigger, .cgr-scrollable-box',
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
                var wrapper = container.closest('.cgr-slider-wrapper');
                if (wrapper) {
                    swiperConfig.navigation = { 
                        nextEl: wrapper.querySelector('.swiper-button-next'), 
                        prevEl: wrapper.querySelector('.swiper-button-prev') 
                    };
                }
            }

            window.cgrSwiperInstance = new Swiper(container, swiperConfig);
        });
    }

    function bootPlugin() {
        ensureModalExists();
        initSwiperSliders();
    }

    document.addEventListener('click', handleReadMoreClick);

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        bootPlugin();
    } else {
        window.addEventListener('DOMContentLoaded', bootPlugin);
    }
})();