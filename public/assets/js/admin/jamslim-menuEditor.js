
function menuEditor(idSelector, settings) {
	var $doc=$(document);
	var $body=$doc.find('body');
	var $holder=$doc.find('#navManager');
    var $main = $holder.find('#' + idSelector);
    var labelEdit = settings.labelEdit || 'E';
    var labelRemove = settings.labelRemove || 'X';
    var $available = $holder.find('#nv_pages');
    var $output = $holder.find('#out');
    var $modal=$doc.find('#editME');
    var $editform = $modal.find('#frmEdit');
    var $mnu_icon = $editform.find('#mnu_icon');
	var $modal_preloader=$('<div class="Preloader"><div class="spinner-icon animate"></div><span>loading...</span></div>');
	var $modal_overlay=$modal.parent();
	var dev_response=false;
	var dbug=false;
	$modal_overlay.append($modal_preloader);
    var itemEdit=0;
    
    if ('data' in settings) {
        var data = jsonToObject(settings.data);
        if (data !== null) {
            var menu = createMenu(data, 0);
            $main.append(menu);
        }
    }
    if(dev_response){
		$holder.find('.devbox').show();
	}
    var iconPickerOpt = settings.iconPicker;
    var options = settings.listOptions;
    if(iconPickerOpt){
		var iconPicker = $editform.find ('#mnu_iconpicker').iconpicker(iconPickerOpt);
		iconPicker.on('change', function (e) {
			$mnu_icon.val(e.icon);
		});
	}
    var inst = $main.sortableLists(options);
    //filter
    myFilter('#filter_a','#nv_pages li');
    /*
    var $fltr=$holder.find('#filter_a');
    if($fltr){
		var $fltr_f=$holder.find('#nv_pages');
		myFilter($fltr,$fltr_f);
	}
	*/
    //clicks
    $('#btnAddExt').on('click', function (e) {
		e.preventDefault();
        $modal.find('.modalTitle').addClass('bg-purple text-white').find('h3').text('Add External Link');
		$editform.find('.btnAddThis').prop('disabled',false);
		$editform.find('.btnUpdate').prop('disabled',true);
		popEdit();
    });
    
    $('#btnOut').on('click', function (e) {
		e.preventDefault();
        var obj = inst.sortableListsToJson();
        var str = JSON.stringify(obj);
        if(dbug){
			$output.text(str);
			return;
		}
		var token=$editform.find('input[name=token]').val();	
		var id=$editform.find('input[name=id]').val();
		var data={'action':'savemenu','data':str,'token':token,'id':id};
        var uri =$editform.prop('action');
		var posting = $.post( uri,data);
		posting.done(function(response){
			var rsp= jQuery.parseJSON(response);
			switch(rsp.action){
				case 'refresh':
					setLocation(rsp.url);
					break;
				case 'alert':
					JQD.utils.renderNotice(rsp.message);
					break;					
				default:
					JQD.utils.renderNotice(response);
					//console.log(response);
			}
			if(dev_response) $output.text(str+"\n"+response);
		},'json');
    });
	
	//general clicks
    $holder.on('click', '.button', function (e){
		e.preventDefault();
		e.stopPropagation();
		var me=$(this);
		var el=me.closest('li');
		if(me.hasClass('btnAdd')){
			$main.append(el);
		}else if(me.hasClass('btnRemove')){
			$available.append(el);
		}else if(me.hasClass('btnEdit')){
			itemEdit=el;
			editItem(el.data());
		}
		return false;
	});
	
	$modal.on('click', '.button', function (e){
		e.preventDefault();
		e.stopPropagation();
		var me=$(this);
		if(me.hasClass('btnAddThis')){
			addItem();
		}else if(me.hasClass('btnUpdate')){
			updateItem();
		}	
		return false
	});

    function editItem(data) {
        //var data = $(item).closest('li').data();
        //console.log(data);
        $modal.find('.modalTitle').removeClass('bg-purple').addClass('bg-dark-blue text-white').find('h3').text('Edit');
        $.each(data, function (p, v) {
            $editform.find('#mnu_' + p).val(v);
        });
        $editform.find('#mnu_text').focus();
        if (data.hasOwnProperty('icon')) {
            iconPicker.iconpicker('setIcon', data.icon);
        }
        $editform.find('.btnUpdate').prop('disabled',false);
        $editform.find('.btnAddThis').prop('disabled',true);
        popEdit(); 
      
    }
	function popEdit(){
		toggleOverlay('open');
		$modal.promise().done(function(r){
			$modal_preloader.hide();
			$modal.foundation('open');
		});
	}
	function popOut(){
		if($body.hasClass('is-reveal-open')) $modal.foundation('close');	
	}
	function toggleOverlay(state){
		if(!state||undefined==state){
			state=($body.hasClass('is-reveal-open'))?'close':'open';
		}
		switch(state){
			case 'open':
				$modal_preloader.show();
				$modal_overlay.show();
				$body.addClass('is-reveal-open');
				break;
			default:	    
				$modal_preloader.hide();	    
				$modal_overlay.hide();
				$body.removeClass('is-reveal-open');
		}
	}
	
    function updateItem() {
        var text = $editform.find('#mnu_text').val();
        if (itemEdit === 0) {
            return;
        }
        //console.log(text);
        var icon = $mnu_icon.val();
        itemEdit.children().children('i').removeClass(itemEdit.data('icon')).addClass(icon);
        itemEdit.find('span.txt').first().text(text);
        itemEdit.data('text', text);
        itemEdit.data('href', $editform.find('#mnu_href').val());
        itemEdit.data('target', $editform.find('#mnu_target').val());
        itemEdit.data('title', $editform.find('#mnu_title').val());
        itemEdit.data('icon', icon);
        reset();
    }

    function addItem() {
        var arrForm = $editform.serializeArray();
        var text = $editform.find('#mnu_text').val();
        var div = newItem(text);
        var li = $('<li>');
        li.data({'ref':'ext','parent':0,'pos':0});
        var reg = new RegExp("^mnu_");
        $.each(arrForm, function (k, v) {
            if (reg.test(v.name)) {
                var name = v.name.replace(reg, '');
                li.data(name, v.value);
            }
        });
        li.addClass('menu-item').append(div);
        $main.append(li);
        reset();
    }
    
    function newItem(text){
        var btnEdit = TButton({classCss: 'button btnEdit', text: labelEdit});
        var btnRemv = TButton({classCss: 'button bg-red btnRemove', text: labelRemove});
        var btnAdd = TButton({classCss: 'button  bg-olive btnAdd', text: labelRemove});
        var grpBtns = $('<div>').addClass('button-group tiny float-right').append(btnEdit).append(btnRemv).append(btnAdd);
        var handle = $('<span>').addClass('grabber bg-orange').html('&nbsp;');
        var textItem = $('<span>').addClass('txt').text(text);
        var iconItem =(iconPickerOpt)?$('<i>').addClass('fa ' + $mnu_icon.val()):false;
        var div = $('<div>');
        div.append(handle);
        if(iconItem) div.append(iconItem).append('&nbsp;');
        div.append(textItem).append(grpBtns);
        return div;		
	}
    
    function reset() {
        $editform[0].reset();
        if(iconPickerOpt){
			iconPicker = $('#mnu_iconpicker').iconpicker(iconPickerOpt);
			iconPicker.iconpicker('setIcon', 'empty');
		}
        $editform.find('.btnUpdate').attr('disabled', true);
        itemEdit = 0;
        popOut();
    }
    /**
     * @param {array} arrayItem Object Array
     * @param {int} depth Depth sub-menu
     * @return {object} jQuery Object
     * */
    function createMenu(arrayItem, depth) {
        var level = (typeof (depth) === 'undefined') ? 0 : depth;
        var $elem;
        if (level === 0) {
            $elem = $main;
        } else {
            $elem = $('<ul>');
        }
        $.each(arrayItem, function (k, v) {
            var isParent = (typeof (v.children) !== 'undefined') && ($.isArray(v.children));
            var $li = $('<li>');
            $li.attr('id', v.text);
            $li.addClass('menu-item').data({'text': v.text,'icon':v.icon,'href':v.href,'ref':v.ref,'parent':v.parent,'pos':v.pos});
            var $div = newItem(v.text);
            $li.append($div);
            if (isParent) {
                $li.append(createMenu(v.children, level + 1));
            }
            $elem.append($li);
        });
        return $elem;
    }
    function jsonToObject(str) {
        try {
            var obj = $.parseJSON(str);
        } catch (err) {
            console.log('The string is not a json valid.');
            return null;
        }
        return obj;
    }
    function TButton(attr) {
        return $("<a>").addClass(attr.classCss).attr("href", "#").text(attr.text);
    }
}
