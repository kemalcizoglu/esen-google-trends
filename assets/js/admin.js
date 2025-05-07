/**
 * Esen Google Trends - Admin JavaScript
 */
(function($) {
    'use strict';

    /**
     * Admin sayfası işlevleri
     */
    var EsenGTAdmin = {
        init: function() {
            this.refreshButton = $('.esen-gt-refresh-button');
            this.trendsContainer = $('#esen-gt-trends-container');
            this.geoFilter = $('#esen-gt-geo-filter');
            
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Trendleri yenileme butonu
            this.refreshButton.on('click', this.refreshTrends.bind(this));
            
            // Sayfa hazır olduğunda
            $(document).ready(this.onReady.bind(this));
        },
        
        onReady: function() {
            // Sayfa hazır olduğunda yapılacak işlemler
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
            
            // Yükleme animasyonu
            this.trendsContainer.html('<div class="esen-gt-loading"></div>');
            
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
                        this.trendsContainer.html('<div class="esen-gt-trends-grid">' + response.data.html + '</div>');
                        
                        // Başarılı mesaj
                        this.showMessage('success', response.data.message);
                    } else {
                        // Hata mesajı
                        this.trendsContainer.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                }.bind(this),
                error: function() {
                    // AJAX hatası
                    this.trendsContainer.html('<div class="notice notice-error"><p>' + esenGT.error + '</p></div>');
                }.bind(this),
                complete: function() {
                    // İşlem tamamlandı, buton durumunu güncelle
                    button.prop('disabled', false);
                    button.text(esenGT.refresh);
                }
            });
        },
        
        /**
         * Mesaj göster
         * 
         * @param {string} type Mesaj türü (success, error, warning, info)
         * @param {string} message Mesaj metni
         */
        showMessage: function(type, message) {
            var noticeClass = 'notice-' + type;
            var noticeHtml = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';
            
            // Önceki mesajları kaldır
            $('.notice').remove();
            
            // Yeni mesajı ekle
            $('.wrap').prepend(noticeHtml);
            
            // WordPress'in dismiss işlevselliğini etkinleştir
            if (typeof wp !== 'undefined' && wp.updates && wp.updates.addDismissButton) {
                wp.updates.addDismissButton();
            }
            
            // Belirli süre sonra mesajı otomatik kaldır
            setTimeout(function() {
                $('.notice').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Başlat
    $(document).ready(function() {
        EsenGTAdmin.init();
    });
    
})(jQuery); 