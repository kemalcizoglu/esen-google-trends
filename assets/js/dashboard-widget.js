/**
 * Esen Google Trends - Dashboard Widget JavaScript
 */
(function($) {
    'use strict';

    /**
     * Dashboard Widget işlevleri
     */
    var EsenGTDashboard = {
        init: function() {
            this.refreshButton = $('.esen-gt-refresh-button');
            this.trendsContainer = $('#esen-gt-trends-container');
            this.geoSelect = $('#esen-gt-geo-select');
            
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Trendleri yenileme butonu
            this.refreshButton.on('click', this.refreshTrends.bind(this));
            
            // Ülke değiştirildiğinde
            this.geoSelect.on('change', this.changeGeo.bind(this));
        },
        
        /**
         * Trendleri yenile
         */
        refreshTrends: function(e) {
            e.preventDefault();
            
            var button = $(e.currentTarget);
            var geo = button.data('geo');
            
            // Buton durumunu güncelle
            button.prop('disabled', true);
            button.text(esenGT.refreshing);
            
            // Yükleme animasyonu - loading text'i "undefined" sorununu önlemek için kontrol edelim
            var loadingText = typeof esenGT.loading !== 'undefined' ? esenGT.loading : 'Yükleniyor...';
            this.trendsContainer.html('<p class="esen-gt-metabox-loading">' + loadingText + '</p>');
            
            // AJAX isteği
            $.ajax({
                url: esenGT.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'esen_gt_refresh_trends',
                    nonce: esenGT.nonce,
                    geo: geo
                },
                success: function(response) {
                    if (response.success) {
                        // Başarılı yanıt
                        this.trendsContainer.html(response.data.html);
                    } else {
                        // Hata mesajı
                        var errorMessage = typeof esenGT.error !== 'undefined' ? esenGT.error : 'Bir hata oluştu';
                        this.trendsContainer.html('<p class="esen-gt-error">' + errorMessage + '</p>');
                    }
                }.bind(this),
                error: function() {
                    // AJAX hatası
                    var errorMessage = typeof esenGT.error !== 'undefined' ? esenGT.error : 'Bir hata oluştu';
                    this.trendsContainer.html('<p class="esen-gt-error">' + errorMessage + '</p>');
                }.bind(this),
                complete: function() {
                    // İşlem tamamlandı, buton durumunu güncelle
                    button.prop('disabled', false);
                    var refreshText = typeof esenGT.refresh !== 'undefined' ? esenGT.refresh : 'Yenile';
                    button.text(refreshText);
                }
            });
        },
        
        /**
         * Ülke değiştir
         */
        changeGeo: function(e) {
            var geo = $(e.currentTarget).val();
            
            // Yenile butonunun geo değerini güncelle
            this.refreshButton.data('geo', geo);
            
            // Trendleri yenile
            this.refreshButton.trigger('click');
        }
    };
    
    // Başlat
    $(document).ready(function() {
        EsenGTDashboard.init();
    });
    
})(jQuery); 