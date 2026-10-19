document.addEventListener("DOMContentLoaded", function() {

    function toggleUIRows() {
        const layoutInput = document.querySelector('input[name="cgr_layout"]:checked');
        if (!layoutInput) return;
        const checkedLayout = layoutInput.value;
        
        document.querySelectorAll('.cgr-layout-card').forEach(card => card.classList.remove('active'));
        layoutInput.closest('.cgr-layout-card').classList.add('active');

        document.querySelectorAll('.cgr-row').forEach(row => {
            if (row.classList.contains('cgr-all') || row.classList.contains('cgr-' + checkedLayout)) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });
        updateLivePreview();
    }

    function hexToRgba(hex, opacity) {
        if (!hex || hex === 'transparent') return 'transparent';
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(char => char + char).join('');
        let r = parseInt(hex.substring(0, 2), 16) || 0;
        let g = parseInt(hex.substring(2, 4), 16) || 0;
        let b = parseInt(hex.substring(4, 6), 16) || 0;
        return `rgba(${r}, ${g}, ${b}, ${opacity})`;
    }

    function getVal(id, fallback) {
        const el = document.getElementById(id);
        return el ? el.value : fallback;
    }

    function isChecked(id) {
        const el = document.getElementById(id);
        return el ? el.checked : false;
    }

    function updateLivePreview() {
        const layoutInput = document.querySelector('input[name="cgr_layout"]:checked');
        if (!layoutInput) return;
        const layout = layoutInput.value;
        
        const navType = getVal('cgr_nav_type', 'dots');
        const gridCols = parseInt(getVal('cgr_grid_columns', '3'));
        
        const sectionBg = getVal('cgr_section_bg', 'transparent');
        const bgColor = getVal('cgr_bg_color', '#ffffff');
        const starColor = getVal('cgr_star_color', '#F2B01E');
        const quoteColor = getVal('cgr_quote_color', '#333333');
        const nameColor = getVal('cgr_name_color', '#333333');
        const dotColor = getVal('cgr_dot_color', '#91C13D');
        const readMoreColor = getVal('cgr_readmore_color', '#91C13D');
        const badgeColor = getVal('cgr_badge_color', '#91C13D');
        const watermarkColor = getVal('cgr_watermark_color', '#333333');
        
        const quoteSize = getVal('cgr_quote_size', '16') + 'px';
        const quoteWeight = getVal('cgr_quote_weight', '400');
        
        const nameSize = getVal('cgr_name_size', '16') + 'px';
        const nameWeight = getVal('cgr_name_weight', '700');
        
        const starSize = getVal('cgr_star_size', '24') + 'px';
        
        const badgeSize = getVal('cgr_badge_size', '11') + 'px';
        const badgeWeight = getVal('cgr_badge_weight', '600');
        
        const watermarkSize = getVal('cgr_watermark_size', '12') + 'px';
        const watermarkWeight = getVal('cgr_watermark_weight', '400');
        
        const padding = getVal('cgr_card_padding', '40') + 'px';
        const radius = getVal('cgr_card_radius', '12') + 'px';

        const showAvatar = isChecked('cgr_show_avatars');
        const showDate = isChecked('cgr_show_date');
        const showBadge = isChecked('cgr_show_badge');
        const showGoogle = isChecked('cgr_show_google_logo');

        document.querySelectorAll('.cgr-preview-avatar').forEach(el => el.style.display = showAvatar ? 'flex' : 'none');
        document.querySelectorAll('.cgr-preview-date').forEach(el => el.style.display = showDate ? 'block' : 'none');
        document.querySelectorAll('.cgr-verified-badge').forEach(el => el.style.display = showBadge ? 'inline-flex' : 'none');
        document.querySelectorAll('.cgr-preview-google-logo').forEach(el => el.style.display = showGoogle ? 'block' : 'none');

        const sectionWrapper = document.getElementById('preview-section-wrapper');
        if (sectionWrapper) sectionWrapper.style.backgroundColor = sectionBg;

        const wrapper = document.getElementById('cgr-preview-layout-wrapper');
        if (!wrapper) return;
        
        const previewNodes = document.querySelectorAll('.cgr-preview-node-item');
        
        if (layout === 'static_grid') {
            wrapper.style.display = 'grid';
            wrapper.style.gridTemplateColumns = `repeat(${gridCols}, 1fr)`;
            wrapper.style.gap = '20px';
            wrapper.style.maxWidth = '100%';
            previewNodes.forEach((item, i) => { item.style.display = (i < gridCols) ? 'block' : 'none'; });
        } else if (layout === 'card_slider') {
            wrapper.style.display = 'grid';
            wrapper.style.gridTemplateColumns = 'repeat(3, 1fr)';
            wrapper.style.gap = '20px';
            wrapper.style.maxWidth = '100%';
            previewNodes.forEach((item, i) => { item.style.display = (i < 3) ? 'block' : 'none'; });
        } else {
            wrapper.style.display = 'block';
            wrapper.style.maxWidth = '850px';
            wrapper.style.margin = '0 auto';
            previewNodes.forEach((item, i) => { item.style.display = (i === 0) ? 'block' : 'none'; });
        }

        document.querySelectorAll('.cgr-card-node').forEach(card => {
            const header = card.querySelector('.cgr-author-profile');
            const stars = card.querySelector('.cgr-stars');
            const quote = card.querySelector('.cgr-quote');
            const readMore = card.querySelector('.cgr-read-more-trigger');
            const badge = card.querySelector('.cgr-verified-badge');
            const googleLogo = card.querySelector('.cgr-preview-google-logo');
            const authorName = card.querySelector('.cgr-preview-author-name');

            if (layout === 'minimal_slider') {
                card.style.background = 'transparent';
                card.style.padding = '0px';
                card.style.boxShadow = 'none';
                
                card.appendChild(stars);
                card.appendChild(quote);
                if(readMore) card.appendChild(readMore);
                
                header.style.display = 'block';
                header.style.textAlign = 'center';
                header.style.marginTop = '20px';
                authorName.style.display = 'block';
                authorName.style.textAlign = 'center';
                card.appendChild(header);

                if(googleLogo) {
                    card.appendChild(googleLogo);
                    googleLogo.style.textAlign = 'center';
                    googleLogo.style.marginTop = '2px';
                    googleLogo.innerHTML = 'Google Review';
                }

                if(badge) {
                    card.appendChild(badge);
                    badge.style.margin = '10px auto 0 auto';
                }
            } else {
                card.style.background = bgColor;
                card.style.padding = padding;
                card.style.borderRadius = radius;
                card.style.boxShadow = '0px 12px 30px rgba(0,0,0,0.06)';
                
                header.style.display = 'flex';
                header.style.textAlign = 'left';
                header.style.marginTop = '0px';
                authorName.style.display = 'inline';
                authorName.style.textAlign = 'left';
                card.insertBefore(header, card.firstChild);
                card.insertBefore(stars, header.nextSibling);
                card.insertBefore(quote, stars.nextSibling);
                if(readMore) card.insertBefore(readMore, quote.nextSibling);
                
                if(badge) {
                    card.appendChild(badge);
                    badge.style.margin = '15px auto 0 0';
                }
                if(googleLogo) {
                    header.querySelector('.cgr-author-title').appendChild(googleLogo);
                    googleLogo.style.textAlign = 'left';
                }
            }
        });

        document.querySelectorAll('.cgr-stars').forEach(el => { el.style.color = starColor; el.style.fontSize = starSize; });
        document.querySelectorAll('.cgr-quote').forEach(el => { el.style.color = quoteColor; el.style.fontSize = quoteSize; el.style.fontWeight = quoteWeight; });
        document.querySelectorAll('.cgr-author-title, .cgr-preview-author-name').forEach(el => { el.style.color = nameColor; el.style.fontSize = nameSize; el.style.fontWeight = nameWeight; });
        document.querySelectorAll('.cgr-preview-google-logo').forEach(el => { el.style.color = watermarkColor; el.style.fontSize = watermarkSize; el.style.fontWeight = watermarkWeight; });
        document.querySelectorAll('.cgr-read-more-trigger').forEach(el => el.style.color = readMoreColor);
        
        document.querySelectorAll('.cgr-verified-badge').forEach(el => {
            el.style.color = badgeColor;
            el.style.backgroundColor = hexToRgba(badgeColor, 0.15);
            el.style.fontSize = badgeSize;
            el.style.fontWeight = badgeWeight;
        });

        const arrowLeft = document.getElementById('preview-arrow-left');
        const arrowRight = document.getElementById('preview-arrow-right');
        const dotsContainer = document.getElementById('preview-dots-container');

        if (arrowLeft) { arrowLeft.style.color = dotColor; arrowLeft.style.display = (navType === 'arrows' || navType === 'both') && layout !== 'static_grid' ? 'block' : 'none'; }
        if (arrowRight) { arrowRight.style.color = dotColor; arrowRight.style.display = (navType === 'arrows' || navType === 'both') && layout !== 'static_grid' ? 'block' : 'none'; }
        if (dotsContainer) { dotsContainer.style.display = (navType === 'dots' || navType === 'both') && layout !== 'static_grid' ? 'block' : 'none'; }
        
        document.querySelectorAll('.preview-dot').forEach((el, i) => { 
            el.style.borderColor = dotColor; 
            el.style.backgroundColor = (i === 0) ? dotColor : 'transparent'; 
        });
    }

    document.querySelectorAll('input[name="cgr_layout"]').forEach(radio => {
        radio.addEventListener('change', toggleUIRows);
    });

    const previewTriggers = [ 
        'cgr_section_bg', 'cgr_bg_color', 'cgr_star_color', 'cgr_quote_color', 
        'cgr_name_color', 'cgr_dot_color', 'cgr_readmore_color', 'cgr_badge_color', 'cgr_watermark_color',
        'cgr_quote_size', 'cgr_name_size', 'cgr_star_size', 'cgr_badge_size', 'cgr_watermark_size',
        'cgr_quote_weight', 'cgr_name_weight', 'cgr_badge_weight', 'cgr_watermark_weight',
        'cgr_card_padding', 'cgr_card_radius', 'cgr_nav_type', 
        'cgr_grid_columns', 'cgr_show_avatars', 'cgr_show_date', 'cgr_show_badge', 
        'cgr_show_google_logo', 'cgr_excerpt_length' 
    ];
    previewTriggers.forEach(id => {
        const el = document.getElementById(id);
        if (el) { 
            el.addEventListener('input', updateLivePreview); 
            el.addEventListener('change', updateLivePreview); 
        }
    });

    toggleUIRows();
});