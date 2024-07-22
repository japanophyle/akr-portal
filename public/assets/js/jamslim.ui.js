'use strict';

function getPromise(func, callback) {
    var deferred = new $.Deferred(func);
    if ($.isFunction(callback)) {
        deferred.then(callback);
    }
    return deferred.promise();
};
var JQD = (function($, window, document, undefined) {
    var $body, $doc, $win, device, zone, json_events, currentMousePos, $calendar, $modal, modal_size, $notice, $datebar, mqSize, timer;
    var $modal2, modal_size2, base_href, viewport = {};
    var modals={'zurbModal':false,'zurbModal2':false,'blur':false};
    var notice_autoclose = true;
    var offCanvas = {
        'el': false,
        state: 'closed'
    };
    var default_target = '#main-holder';
    var rc_loaded = {};
    var msgr = {
        type: 'info',
        message: false,
        close: false
    };
    var DB = {
        events: [],
        recurr: []
    };
    var totals = {
        qty: 0,
        value: 0,
        items: 0
    };
    var $dataTable;
    var tinyModal = false;
    var $debugbar = false;
    var $helpbar = false;
    return {
        go: function(options) {
            for (var i in JQD.init) {
                JQD.init[i](options);
            }
        },
        init: {
            desktop: function(options) {
                $doc = $(document);
                $win = $(window);
                $body = $doc.find('body');
                base_href = $doc.find('base').first().prop('href');
                JQD.inits.initNavigation();
                $calendar = $doc.find('#calendar');
                viewport.h = $win.height();
                viewport.w = $win.width();
                $notice = $doc.find('#messenger');
                $modal = $doc.find('#zurbModal');
                $modal2 = $doc.find('#zurbModal2');
                $modal.on('open.zf.reveal', function() {
                    JQD.utils.modalOpened($modal);
                }).on('closed.zf.reveal', function() {
					JQD.utils.modalClosed($modal);
				});
                $modal2.on('open.zf.reveal', function() {
                    JQD.utils.modalOpened($modal2);
                }).on('closed.zf.reveal', function() {
					JQD.utils.modalClosed($modal2);
				});
                $modal.removeClass('large medium small tiny full');
                $modal2.removeClass('large medium small tiny full');
                $doc.on('click', '.reveal .closer', function(e) {
                    e.preventDefault();
                });
                offCanvas.el = $doc.find('#offCanvas');
            },
            defaults: function(options) {
				JQD.utils.initResize();
                JQD.inits.initLoadME('.loadME');
                JQD.inits.initLoadOC('.loadOC');
                JQD.inits.initOverLoad('.overLoad');
                JQD.inits.initGotoME('.gotoME');
                JQD.inits.initGotoURL('.gotoURL');
                JQD.inits.initSubmitME('.submitME');
                JQD.inits.initTriggerSubmit('button.trigger_submit');
                JQD.inits.initSelectME('.insertME');
                JQD.inits.initGetME('.getME');
                JQD.inits.initBackME('.backME');
                JQD.inits.initAjaxForm('form.ajaxForm');
                JQD.inits.initLazyLoad('img.lazyload');
                JQD.inits.initloadIMG('.loadIMG');
                JQD.inits.initProductSelector();
                JQD.inits.initMainApp(options);
                JQD.utils.setTimer('JQD.utils.closeNotice()');
            },
            optionals: function(options) {
                if (options && options != undefined) {
                    if (options.editor) JQD.ext.initEditor(options.editor);
                    if (options.color) JQD.ext.initColorPicker();
                    if (options.filter) JQD.ext.initFilter();
                    if (options.time) JQD.ext.initTimePicker();
                    if (options.back) JQD.inits.initBack();
                    if (options.datatable) JQD.inits.initDataTable(options.datatable);
                    if (options.debugbar) JQD.utils.debugBar('debugBar');
                    if (options.helpbar) JQD.utils.debugBar('helpBar');
                }
            }
        },
        ext: {
            initFilter: function() {
                JQD.ext.filterThis('#filter', false);
            },
            filterThis: function(_filter, _list) {
                myFilter(_filter, _list);
            },
            initTimePicker: function() {
                timePicker2('.timepicker-table');
            },
            initColorPicker: function() {
                $doc.find('.cpicker').colorPicker();
            },
            initEditor: function(ed_id) {
                var eds = $doc.find(ed_id);
                var opts = {
                    svgPath: './gfx/icons.svg',
                    semantic: true,
                    btns: [
                        ['strong', 'em', 'del'],
                        ['unorderedList', 'orderedList'],
                        ['removeformat', 'jamCleaner']
                    ]
                };
                if (eds.length > 0) {
                    eds.each(function(i, v) {
                        var me = $(v);
                        var mid = me.prop('id');
                        var mt = me.data('mode');
                        var m = $('#' + mid);
                        switch (mt) {
                            case 'full':
                                opts.btns = [
                                    ['formatting', 'jamBox'],
                                    ['strong', 'em', 'del', 'jamColor'],
                                    ['jamLink', 'mediaBrowser'],
                                    ['unorderedList', 'orderedList'],
                                    ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
                                    ['horizontalRule'],
                                    ['removeformat', 'jamCleaner'],
                                    ['fullscreen']
                                ];
                                opts.semantic = true;
                                break;
                            case 'email':
                                opts.semantic = false;
                        }
                        m.trumbowyg('destroy');
                        m.trumbowyg(opts).on('tbwchange', function(contents, $editable) {
                            m.html(m.trumbowyg('html'));
                        });
                    });
                } else {}
            },
            initMyTable: function(filter_id, table_id) {
                var $table = $(table_id);
                var $table_rows = $table.find('tbody tr');
                $table_rows.on('click', function() {
                    $table_rows.removeClass('active');
                    $(this).addClass('active');
                });
                myFilter(filter_id, table_id + ' tbody tr');
                $table.mySorter();
            },
            initMGrid: function(tableID) {
                var $TBL = $doc.find('#' + tableID);
                if ($TBL.length > 0) {
                    var ExportButtons = $('.export_button');
                    $TBL.dragtable({
                        scroll: true
                    });
                    ExportButtons.on('click', function(e) {
                        e.preventDefault();
                        var ref = $(this).data('ref');
                        var EXP = $TBL.tableExport({
                            exportButtons: false
                        });
                        var exportData = EXP.getExportData()[tableID][ref];
                        EXP.export2file(exportData.data, exportData.mimeType, exportData.filename, exportData.fileExtension);
                        EXP.remove();
                    });
                }
            },
            initMGrid_sort: function(tableID) {
                var $TBL = $doc.find('#' + tableID);
                if ($TBL.length > 0) {
                    var opt = {
                        scroll: true
                    };
                    var handle = $TBL.data('handle');
                    var powers = $TBL.data('power');
                    var gorder = $TBL.data('order');
                    if (handle) {
                        $TBL.addClass('handlerTable');
                        var chk = $TBL.find('.' + handle);
                        if (chk.length == 0) {
                            var h = '';
                            if (powers) {
                                chk = $TBL.find('.power');
                                if (chk.length == 0) h += '';
                            }
                            h += '';
                        }
                        opt['dragHandle'] = '.' + handle;
                        if (powers) opt['sortedList'] = $(gorder);
                        var th = $TBL.find('th');
                        th.prepend(h).promise().done(function() {
                            $TBL.dragtable(opt);
                        });
                    } else {
                        $TBL.dragtable(opt);
                    }
                }
            }
        },
        inits: {
            initloadIMG: function(cls) {
                loadIMG(cls);
            },
            initMainApp: function(opts) {
                if (opts.load) {
                    if (opts.load.url) {
                        JQD.utils.loadContents(opts.load.url);
                    } else if (opts.load.content) {
                        $doc.find(opts.load.target).html(opts.load.content);
                        var h = viewport.h - 280;
                        $doc.find('#main-holder .modal-body .tabs-content').css('max-height', h);
                    }
                } else if (!opts.datatable) {
                    $doc.find(default_target).html('Loading Failed... Because I have not been told what to load :)');
                }
            },
            initDataTable: function(opts) {
                if (opts.url.length > 5) {
                    var req = JQD.utils.getJsonData(false, opts.url, 'GET');
                    req.done(function(dt) {
                        if (undefined !== dt.debug) {
                            JQD.utils.debugBar_log(dt.debug);
                        }
                        if (dt.type && dt.type === 'message') {
                            msgr.message = dt.message;
                            msgr.type = dt.message_type;
                            JQD.utils.renderNotice(msgr);
                            $doc.find(opts.options.target).html(dt.message);
                        } else if ((dt.type && dt.type === 'swap')) {
                            JQD.utils.swapContents(dt.contents, dt.element);
                            if (dt.message) {
                                msgr.message = dt.message;
                                msgr.type = dt.message_type;
                                JQD.utils.renderNotice(msgr);
                            }
                        } else {
                            var srt = (dt.sort) ? dt.sort : opts.sort;
                            var dtx = (dt.data) ? dt.data : dt;
                            if (dt.date_format != undefined) {
                                opts.options.date_format = dt.date_format;
                            }
                            var table = jqTable.render(dtx, opts.options, srt);
                            table.done(function(d) {
                                $doc.find(opts.options.target).html(d);
                            }).then(function(d) {
                                var h = viewport.h - 280;
                                if (h < 300) h = 300;
                                $doc.find('table.dataTable').tableScroll({
                                    height: h
                                }).stupidtable({
                                    fixedhead: '.tablescroll_head',
                                    fixedbody: '.tablescroll_body'
                                });
                            });
                        }
                    });
                } else {
                    var table = jqTable.render(opts.data, opts.options, opts.sort);
                    table.done(function(d) {
                        $doc.find(opts.options.target).html(d);
                    }).then(function(d) {
                        $dataTable = $doc.find('table.dataTable').eq(0);
                        var h = viewport.h - 250;
                        if (h < 350) h = 350;
                        $dataTable.tableScroll({
                            height: h
                        }).stupidtable({
                            fixedhead: '.tablescroll_head',
                            fixedbody: '.tablescroll_body'
                        });
                    });
                }
            },
            initDateBar: function(header) {
                if (header) {
                    var insert = false;
                    var currentTime = new Date();
                    var str_time = currentTime.format('HH:MM tt');
                    var str_date = currentTime.format('dddd, mmmm dS, yyyy');
                    if (insert) {
                        $datebar = $(str_date + ' ' + str_time);
                        header.prepend($datebar);
                    } else {
                        $datebar = header.find('div#datebar');
                    }
                    if ($datebar && $datebar != undefined) {
                        JQD.utils.updateClock();
                        setInterval('JQD.utils.updateClock()', 50000);
                    }
                }
            },
            initBack: function() {
                $doc.find('#cBack').on('click', function(e) {
                    e.preventDefault();
                    window.history.back();
                });
            },
            initBackME: function(backclass) {
                $doc.on('click', backclass, function(e) {
                    e.preventDefault();
                    window.history.back();
                });
            },
            initLazyLoad: function(cls) {
                var $lz = $doc.find(cls);
                $lz.lazyload();
            },
            initLoadME: function(loadclass) {
                JQD.utils.loadModal_init(loadclass, $modal);
            },
            initOverLoad: function(loadclass) {
                JQD.utils.loadModal_init(loadclass, $modal2);
            },
            initLoadOC: function(loadclass) {
                $doc.on('click', loadclass, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var url = $(this).data('ref');
                    var rq = JQD.utils.loadContents(url);
                    rq.then(function() {
                        offCanvas.el.foundation('open', event, trigger);
                    });
                });
            },
            initGotoME: function(loadclass) {
                $doc.on('click', loadclass, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var ref = $(this).data('ref');
                    JQD.utils.setLocation(ref);
                });
            },
            initGotoURL: function(loadclass) {
                $doc.on('click', loadclass, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
					var ref = $(this).data('ref');
					var anchor = document.createElement('a');
					anchor.href = ref;
					anchor.target='_blank';
					anchor.click();
                });
            },
            initGetME: function(loadclass) {
                $doc.on('click', loadclass, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var me = $(this);
                    var ref = me.data('ref');
                    var r = JQD.utils.getJsonData(false, ref, 'GET');
                    r.done(function(o) {
                        if (o.message) {
                            var type = (o.type) ? o.type : 'primary';
                            JQD.utils.renderNotice({
                                message: o.message,
                                'type': type
                            });
                        }
                        if (o.refresh) location.reload();
                        if (o.target) JQD.utils.swapContents(o.content, o.target);
                        if (o.cart) togCartLink(o.cart);
                    });
                });
            },
            initSelectME: function(loadclass) {
                $doc.on('click', loadclass, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var me = $(this);
                    var ref = me.data('ref');
                    var target = me.data('target');
                    var alt = me.data('alt');
                    var bg = me.data('bg');
                    if (ref && ref != undefined) {
                        alt = (!alt || alt == undefined) ? 'Selected' : alt;
                        $doc.find(target).val(ref);
                        $doc.find(target + '_img').attr('src', ref);
                        $doc.find(bg).css('background-image', 'url(' + ref + ')');
                        $doc.find(target + '_alt').html($.base64('atob', alt, true));
                        JQD.utils.closeModal($modal);
                    } else {
                        JQD.utils.renderNotice({
                            message: 'Sorry, the link is not valid',
                            type: 'warning'
                        });
                    }
                });
            },
            initSubmitME: function(loadclass) {
                $doc.on('click', loadclass, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var me = $(this);
                    var ref = me.data('ref');
                    var act = me.data('act');
                    if (ref && ref != undefined) {
                        var frm = $doc.find('form#' + ref);
                        if (act && act != undefined) frm.append('');
                        frm.submit();
                    } else {
                        JQD.utils.renderNotice({
                            message: 'Sorry, I can\'t sumbit that form...',
                            type: 'warning'
                        });
                    }
                });
            },
            initTriggerSubmit: function(loadclass) {
                $doc.on('click', loadclass, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var formref = $(this).data('ref');
                    if (undefined == formref || formref == '' || !formref) {
                        formref = '#form1';
                    } else {
                        formref = '#' + formref;
                    }
                    somethingChanged = false;
                    var f = $doc.find(formref);
                    var b = f.find('input[name="submiter"]').trigger('click');
                    if (b !== undefined && b.length > 0) {
                        b.trigger('click');
                    } else {
                        f.submit();
                    }
                });
            },
            initNavigation: function() {
                var header = $doc.find('#headspace');
                var $sbox = $doc.find('div#top-bar-search');
                $doc.find('#responsive-menu a.nogo').click(function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                });
                $doc.find('.multilevel-accordion-menu .is-accordion-submenu-parent > a.is-active').trigger('click');
                if (Foundation.MediaQuery.atLeast('large')) {
                    $sbox.show();
                } else {
                    $sbox.hide();
                }
                $win.on('changed.zf.mediaquery', function(event, newSize, oldSize) {
                    JQD.utils.setResponsiveToggle($sbox);
                });
            },
            initToggleTicks: function() {
                var togs = $doc.find('button.toggleSelection');
                togs.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var me = $(this);
                    var c = $('#' + me.data('target'));
                    var el = c.find('.' + me.data('class'));
                    el.each(function() {
                        this.checked = !this.checked;
                    });
                });
            },
            initJumper: function() {
                var $jpr = $doc.find('#jumper');
                var $jpb = $doc.find('#jumpME');
                $jpb.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var s = $jpr.val();
                    JQD.utils.doJumper(s);
                });
                $('#jumper').keypress(function(e) {
                    if (e.which == 13) {
                        e.preventDefault();
                        var s = $jpr.val();
                        JQD.utils.doJumper(s);
                    }
                });
            },
            initAjaxForm: function(cls) {
                $doc.on('submit', cls, function(ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    var $frm = $(this);
                    var fid = $frm.attr('id');
                    switch (fid) {
                        case 'users':
                            chk = $frm.find('#mlistTable_filter input').val();
                            if (chk === undefined || chk === '') {
                                return;
                            } else {
                                var answer = confirm('Only visible items are saved...\nClick "Cancel" if you want to clear the filter before continuing?');
                                if (answer) {
                                    return;
                                } else {
                                    return false;
                                }
                            }
                            break;
                        default:
                            JQD.utils.postForm($frm);
                            return false;
                    }
                });
            },
            initProductSelector: function() {
                var tbl_prod_id = 'table#products tbody';
                var tbl_prod = $doc.find(tbl_prod_id);
                var tbl_pay = $doc.find('table#tbl_add_sale');
                if (tbl_prod.length < 1 || tbl_pay.length < 1) return false;
                var inserter = $doc.find('#insert_product');
                var selector = $doc.find('select#select_product');
                var order_value = tbl_pay.find('input[name="sls_Order_Value"]');
                var payment_value = tbl_pay.find('input[name="sls_Payment_Value"]');
                var shipping_value = tbl_pay.find('input[name="sls_Handling_Value"]');
                var updateValues = function() {
                    var prods = tbl_prod.find('input._prods').serialize();
                    var qty = tbl_prod.find('input._qty').serialize();
                    var shipping = shipping_value.val();
                    prods = get_query(prods);
                    qty = get_query(qty);
                    totals.value = 0;
                    totals.qty = 0;
                    totals.items = 0;
                    for (var i in prods) {
                        if (i) {
                            var qs = i.replace('products', 'qty');
                            var ts = i.replace('products', 'tot');
                            var lv = (prods[i] * qty[qs]);
                            totals.value += lv;
                            totals.qty += qty[qs];
                            totals.items++;
                            lv = (lv / 100);
                            tbl_prod.find('td.' + ts).html('£' + lv.toFixed(2) + '');
                        }
                    }
                    var tv = (totals.value / 100);
                    var pv = (totals.value + parseInt(shipping * 100));
                    pv = (pv / 100);
                    order_value.val(tv.toFixed(2));
                    payment_value.val(pv.toFixed(2));
                };
                $doc.on('change', 'input._qty', function() {
                    updateValues();
                });
                $doc.on('click', tbl_prod_id + ' button.prod-remove', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var r = $(this).data('ref');
                    var rw = tbl_prod.find(r);
                    rw.fadeOut(333, function() {
                        rw.remove();
                        updateValues();
                    });
                });
                shipping_value.on('change', function() {
                    updateValues();
                });
                inserter.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var o = selector.find('option:selected');
                    var id = o.val();
                    var price = o.data('price');
                    var fprice = price / 100;
                    var row = '' + id + '' + o.text() + '' + fprice.toFixed(2) + '';
                    $notice.html(msg);
                    var time = 4000;
                    if (parseInt(args.close) > 1999) {time = parseInt(args.close);}
                    if (notice_autoclose || args.close){JQD.utils.setTimer('JQD.utils.closeNotice()', time);}
                });
            },
          },
          utils:{
            loadModal_init: function (loadclass, modal, reload) {
                $doc.on('click', loadclass, function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var me = $(this);
                        var id = me.prop('id');
                        var href = me.attr('href');
                        var ref = me.data('ref');
                        var img = me.is('img');
                        var url = href;                        
                        if (img) {
                            if (!url || url === undefined) url = me.attr('src');
                            if (url) url = loader + '?c=image&a=' + url;
                        } else {
                            if (!url || url === undefined) url = ref;
                        }
                        
                        var size = me.data('size');
                        if (size === undefined) size = 'medium';
                        modal_size = size;
                        if (url && url !== undefined) {
                            JQD.utils.openModal(modal, url,reload);
                        } else {
                            JQD.utils.renderNotice({message: 'Sorry, the link is not valid', type: 'warning'});
                        }
                        return false;
                    });
            },
            openModal: function (modal, url,reload) {				
                if (!modal || undefined == modal) modal = $modal;
                var mid=modal[0].id;
                var opened=modals[mid];
                //console.log(mid,opened);
                if(mid==='zurbModal' && opened){
					if(!reload) modal=$modal2;
				}
               // JQD.utils.debugBar_log('[JS-DEBUG: openModal] (modal:' + modal.prop('id') + ', url:' + url + ')');
                $.ajax(url).done(function (r) {
					var c = ($.type(r) === 'string')? r: r.content;
					//if (undefined !== r.debug) JQD.utils.debugBar_log(r.debug);
					modal.removeClass('large medium small tiny full');
					modal.addClass(modal_size).html(c).foundation('open');
					$doc.find('.blurME').addClass('blurIT');
					modals.blur=true;
				});
            },
            closeModal: function (modal) {
                if (!modal || undefined === modal) {
                    modal = ($modal2.is(':visible'))? $modal2: $modal;
                }
                if(modals.zurbModal && modals.zurbModal2){
				}else if(modals.blur){
					$blurme.removeClass('blurIT');
					modals.blur=false;
				}
                modal.foundation('close');
             },
            modalOpened: function (modal) {
				var mid=modal[0].id;
				modals[mid]=true;
                if (modal_size !== 'full') {
                    var mh = (viewport.h - 20);
                    var ih = (mh - 240);
                    modal.find('div.tabs-content').css('max-height', ih);
                }
                JQD.utils.scrollToTop(false, modal);
            },
            modalClosed: function (modal) {
				var mid=modal[0].id;
				console.log(mid);
				modals[mid]=false;
                modal.removeClass('large medium small tiny full');
                //fallback to unblur if escape was pressed
                if(!modals.zurbModal && !modals.zurbModal2){
					if(modals.blur){
						//$blurme.removeClass('blurIT');
					}
				}
            },
            setTimer: function(f, t) {
                var time = t || 4000;
                if (f && f !== undefined) {
                    timer = window.setTimeout(f, time);
                }
            },
            closeNotice: function() {
                var chk = $notice.find('div.callout');
                if (chk && undefined != chk) chk.fadeOut('333');
                window.clearTimeout(timer);
            },
            doJumper: function(str) {
                if (str.length > 2) {
                    var jd = str.split('/');
                    var chk = parseInt(jd[0]);
                    var ref;
                    if (isNaN(chk)) {
                        ref = '&act=find&what=' + str;
                    } else {
                        ref = '&month=' + parseInt(jd[0]) + '&year=' + parseInt(jd[1]);
                    }
                    JQD.utils.setLocation(ajax_url + ref);
                } else {
                    msgr.message = 'The search keyword must be at least 3 characters long.';
                    msgr.type = 'warning';
                    JQD.utils.renderNotice(msgr);
                }
            },
            updateClock: function() {
                var currentTime = new Date();
                var str_time = currentTime.format('HH:MM tt');
                $datebar.find('div#dateBar-clock').html(str_time);
            },
            setMediaQuerySize: function() {
                mqSize = Foundation.MediaQuery.current;
            },
            setResponsiveToggle: function($el) {
                JQD.utils.setMediaQuerySize();
                if (mqSize === 'large') {} else {}
                if (Foundation.MediaQuery.atLeast('large')) {
                    $el.show();
                } else {
                    $el.hide();
                }
            },
		    calendarStack: function(){
				 var cal=$doc.find('#calendar-table .calendar');
				 if (!cal.length) return false;
				 if (cal.hasClass('mini_calendar')) return false;
				 if (Foundation.MediaQuery.atLeast("large")){
					 cal.removeClass('stack');
				 }else{
					 cal.addClass('stack');
				 }
			},                
			setMainViewportDimensions: function() {
				var t = $doc.find("#mainWrapper"),
					e = t.find(".cell.header").height(),
					i = t.find(".cell.footer").height(),
					a = $doc.find("#resultList"),
					o = $doc.find("#side-menu"),
					s = viewport.h - (e + i);
					if (a.length) {
						var r = a.find(".mpFilter").height();
						a.find("#searchResults").css("height", s - r + "px");
					}
			},
			initResize: function() {
				var t = JQD.utils._debounce(function() {
					//console.log("resize"), 
					JQD.utils.setMediaQuerySize(), 
					//JQD.utils.setMainViewportDimensions(),
					JQD.utils.calendarStack()
				}, 250);
				JQD.utils.setMediaQuerySize(), JQD.utils.setMainViewportDimensions(),JQD.utils.calendarStack(), $win.on("resize", t)
			},
            setLocation: function(ref) {
                window.location.href = ref;
            },
			getJsonData: function (args, url, req_type) {
				var response = $.ajax({type: req_type, url: url, data: args, dataType: 'json'})
					.fail(function () {
						msgr.message = 'Sorry, there was a problem loading the data...';
						msgr.type = 'alert';
						JQD.utils.renderNotice(msgr);
					});
				return response;
			},
		    renderNotice: function (args) {
				if (args && args !== undefined && args.message) {
					if (undefined === args.type)args.type = 'primary';
					var msg = '<div class="callout ' + args.type + '" data-closable><div class="callout-text">' + args.message + '</div><button class="close-button" aria-label="close" type="button" data-close><span aria-hidden="true">&times;</span></button></div>';
					$notice.html(msg);
					var time = 4000;
					if (parseInt(args.close) > 1999) time = parseInt(args.close);
					if (notice_autoclose || args.close) JQD.utils.setTimer('JQD.utils.closeNotice()', time);
				}
			},
            swapContents: function(contents, element) {
                var el = (element === 'modal') ? $modal : $doc.find(element);
                if (tinyModal) {
                    if (tinyModal.length > 0) {
                        removeTinyModal();
                    }
                }
                el.html(contents);
            },
            scrollToTop: function(el, modal) {
                if (modal) {
                    var p = modal.find('.tabs-content .tabs-panel');
                    if (p.length > 0) {
                        p.each(function() {
                            var m = $(this);
                            var st = (m.hasClass('is-active'));
                            m.css({
                                'visability': 'hidden'
                            }).addClass('is-active').scrollTop(0);
                            if (!st) m.removeClass('is-active');
                            m.css({
                                'visability': 'visible'
                            });
                        });
                    } else {
                        modal.find('.modalBody,.modal-body').scrollTop(0);
                    }
                } else {
                    $doc.find(el).scrollTop(0);
                }
            },
            multiSelector: function(opts) {
                jqMultiSelect.render(opts);
            },
            debugBar: function(el_id) {
                if (el_id === 'debugBar' && $debugbar) return;
                if (el_id === 'helperBar' && $helpbar) return;
                var $DBG = $doc.find('#' + el_id);
                var toggleit = function(act) {
                    if (act === 'open') {
                        $Panel.data('state', 'open').show(555, function() {
                            $Toggler.html('X')
                        });
                    } else {
                        $Panel.data('state', 'closed').hide(555, function() {
                            $Toggler.html('?')
                        });
                    }
                };
                if ($DBG !== undefined) {
                    var $Panel = $DBG.find('.debug-panels')[0];
                    var $SECTIONS = $DBG.find('.debug-panel');
                    if ($Panel) $Panel = $($Panel);
                    var $Toggler = $DBG.find('.debug-toggle');
                    $DBG.find('.debug-nav-button').on('click', function(e) {
                        if ($Panel.data('state') !== 'open') {
                            toggleit('open');
                        }
                        e.preventDefault();
                        var m = $(this);
                        var r = m.data('ref');
                        $SECTIONS.hide();
                        $DBG.find(r).show();
                    });
                    $Toggler.on('click', function(e) {
                        if ($Panel.data('state') !== 'open') {
                            toggleit('open');
                        } else {
                            toggleit('close');
                        }
                    });
                    if (el_id === 'debugBar') {
                        $debugbar = $DBG;
                    } else if (el_id === 'helpBar') {
                        $helpbar = $DBG;
                    }
                }
            },
            debugBar_log: function(str) {
                if ($debugbar && $debugbar !== undefined) {
                    var lg = (typeof(str) !== 'string') ? str.log : '[' + dateFormat() + '] ' + str;
                    var $DBG = $doc.find('#slimDebug');
                    var panel = $debugbar.find('#slim_13');
                    if (lg && lg !== '' && lg !== undefined) {
                        lg = '⇒ ' + lg + ' ';
                        panel.find('div').eq(0).prepend(lg);
                    }
                }
            },
			_debounce: function(t, e, i) {
				var a;
				return function() {
					var n = this,
						o = arguments,
						s = i && !a;
					clearTimeout(a), a = setTimeout(function() {
						a = null, i || t.apply(n, o)
					}, e), s && t.apply(n, o)
				}
			}
           
        }
    };
})(jQuery, this, this.document);
