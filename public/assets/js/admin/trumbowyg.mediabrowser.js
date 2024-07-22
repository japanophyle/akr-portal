/* ===========================================================
 * trumbowyg.mediaBrowser.js v1.0
 * media browser plugin for Trumbowyg
 * http://www.jamtechsolutions.co.uk
 * ===========================================================
 * Author : jamtech
 */

(function ($) {
    'use strict';

    var defaultOptions = {
        proxy: 'admin/media/browser/',
        urlFiled: 'url',
        data: [],
        success: undefined,
        error: undefined
    };

    $.extend(true, $.trumbowyg, {
        langs: {
            en: {
                mediaBrowser: 'Media Browser',
                mediaBrowserError: 'Error'
            },
        },

        plugins: {
            mediaBrowser: {
                init: function (trumbowyg) {
                    trumbowyg.o.plugins.mediaBrowser = $.extend(true, {}, defaultOptions, trumbowyg.o.plugins.mediaBrowser || {});

                    var btnDef = {
						ico: 'insert-image',
                        fn: function () {
                            var $modal = trumbowyg.openModalInsert(
                                // Title
                                trumbowyg.lang.mediaBrowser,

                                // Fields
                                {
                                    url: {
                                        label: 'URL',
                                        required: true,
                                        type:'text" id="tr_url'
                                    },
                                    browse:{
										label: 'Browser',
										type:'button" data-target="tr_url" id="slimBrowser" class="button button-purple',
										value:'Media Browser',
									}
                                },

                                // Callback
                                function (data) {
									if(data.url && data.url!==''){
										trumbowyg.execCmd('insertImage', data.url, undefined, true);
										setTimeout(function () {
											trumbowyg.closeModal();
										}, 250);
									}else{
									    trumbowyg.addErrorOnModalField(
											$('#tr_url', $modal),
											'select an image'
									    );
									}
                                }
                            );
                        }
                    };

                    trumbowyg.addBtnDef('mediaBrowser', btnDef);
                    $(document).on('click','#slimBrowser',function(e){
						var me=$(this);
						var target=me.data('target');
						JQD.utils.openModal(false,'admin/media/image/select/?link='+target);
					});
                }
            }
        }
    });
})(jQuery);
