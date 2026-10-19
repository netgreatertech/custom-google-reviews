document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Read More Toggle Logic
    document.querySelectorAll('.cgr-read-more-trigger').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            const targetEl = document.getElementById(targetId);
            
            if (targetEl.classList.contains('cgr-expanded')) {
                targetEl.classList.remove('cgr-expanded');
                this.textContent = this.getAttribute('data-more');
            } else {
                targetEl.classList.add('cgr-expanded');
                this.textContent = this.getAttribute('data-less');
            }
            
            if (typeof window.cgrSwiperInstance !== 'undefined') {
                window.cgrSwiperInstance.updateAutoHeight(200);
            }
        });
    });

    // 2. Swiper Initialization (Bulletproof Data Attribute Method)
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

        // Dots Configuration
        if (navType === 'dots' || navType === 'both') {
            swiperConfig.pagination = { 
                el: container.querySelector('.swiper-pagination'), 
                clickable: true 
            };
        }
        
        // Arrows Configuration (Arrows are outside container in the wrapper)
        if (navType === 'arrows' || navType === 'both') {
            let wrapper = container.closest('.cgr-slider-wrapper');
            if (wrapper) {
                swiperConfig.navigation = { 
                    nextEl: wrapper.querySelector('.swiper-button-next'), 
                    prevEl: wrapper.querySelector('.swiper-button-prev') 
                };
            }
        }

        // Slight delay ensures the DOM CSS is fully painted before calculating heights
        setTimeout(function() {
            window.cgrSwiperInstance = new Swiper(container, swiperConfig);
        }, 50);
    });
});