/**
 * Esen Google Trends - Metabox JavaScript
 */
(function($) {
    'use strict';

    /**
     * Metabox işlevleri
     */
    var EsenGTMetabox = {
        init: function() {
            this.refreshButton = $('.esen-gt-refresh-button');
            this.trendsContainer = $('#esen-gt-metabox-trends-container');
            this.geoSelect = $('#esen-gt-metabox-geo');
            
            this.bindEvents();
            this.loadTrends();
        },
        
        bindEvents: function() {
            // Trendleri getir butonu
            this.refreshButton.on('click', this.loadTrends.bind(this));
            
            // Ülke değiştirildiğinde
            this.geoSelect.on('change', this.updateGeo.bind(this));
        },
        
        /**
         * Trendleri yükle
         */
        loadTrends: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            var geo = this.geoSelect.val();
            
            // Buton durumunu güncelle
            this.refreshButton.prop('disabled', true);
            
            // Yükleme animasyonu
            this.trendsContainer.html('<p class="esen-gt-metabox-loading">' + esenGT.loading + '</p>');
            
            // AJAX isteği
            $.ajax({
                url: esenGT.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'esen_gt_get_trend_details',
                    nonce: esenGT.nonce,
                    geo: geo
                },
                success: function(response) {
                    if (response.success) {
                        // Başarılı yanıt
                        this.trendsContainer.html(response.data.html);
                    } else {
                        // Hata mesajı
                        this.trendsContainer.html('<p class="esen-gt-error">' + response.data.message + '</p>');
                    }
                }.bind(this),
                error: function() {
                    // AJAX hatası
                    this.trendsContainer.html('<p class="esen-gt-error">' + esenGT.error + '</p>');
                }.bind(this),
                complete: function() {
                    // İşlem tamamlandı, buton durumunu güncelle
                    this.refreshButton.prop('disabled', false);
                }.bind(this)
            });
        },
        
        /**
         * Ülke güncelle
         */
        updateGeo: function() {
            var geo = this.geoSelect.val();
            this.refreshButton.data('geo', geo);
            this.loadTrends(); // Ülke değiştiğinde otomatik olarak trendleri yenile
        }
    };
    
    // Başlat
    $(document).ready(function() {
        EsenGTMetabox.init();
    });
    
})(jQuery); 