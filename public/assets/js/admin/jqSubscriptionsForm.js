;var jqActiveInvoice = (function($, window, document, undefined) {
	var $doc=$(document);
	var totals={qty:0,value:0,items:0};
	var $invoice=false;//active invoice container
	var $prods,$order_value,$order_qty,$tbl_prod;
	var options={
		'form_id':'#subs',
		'prod_selector':'.selectProduct',
		'remover':'button.prod-remove',
		'row_container':'table#products tbody',
		'input_product':'._prods',
		'tot_value':'#total-value',
		'tot_qty':'#total-qty',
		'tot_items':'#total-items'
	};
	function init(){
		$invoice=$doc.find(options.form_id);
		if($invoice.length>0){
			$tbl_prod=$invoice.find(options.row_container);
			if($tbl_prod.length>0){				
				initEvents();
				$order_qty=$invoice.find(options.tot_qty);
				$order_value=$invoice.find(options.tot_value);
			}else{
				JQD.utils.renderNotice({message:'Sorry, ActiveInvoice could not be started...',type:'warning'});
			}
		}else{			
			JQD.utils.renderNotice({message:'Sorry, '+options.form_id+' could not be found...',type:'warning'});
		}		
	}

	function initEvents(){
		//selector
		//JQD.utils.renderNotice({message:'init...',type:'primary'});
		$doc.on('click',options.prod_selector,function(e){
			e.preventDefault();
			e.stopPropagation();
			var me=$(this);
			var ref=me.data('ref');
			var url=me.data('url');
			var target=me.data('target');
			var alt=me.data('alt');
			if(ref && ref !=undefined){
				var r='<tr id="prod_'+ref+'"><td>'+ref+' <input class="_prods" type="hidden" name="products['+ref+']" value="'+me.data('price')+'"/></td><td>'+alt+'</td><td>'+me.data('price')+'</td><td>1</td><td>'+me.data('price')+'</td><td><button class="button small prod-remove alert" type="button" data-ref="#prod_'+ref+'"><i class="fi-x"></i> Remove</button></td></tr>';
				$invoice.find('#'+target).before(r);
				updateValues();	
			}else{
				JQD.utils.renderNotice({message:'Sorry, the link is not valid',type:'warning'});
			}
		});
		$invoice.on('click',options.remover,function(e){
			e.preventDefault();
			e.stopPropagation();
			var me=$(this);
			var ref=me.data('ref');			
			var del=confirm('Do you want to remove item #'+ref.replace('#prod_','')+' from this order?');
			if(del){
				var $del=$invoice.find(ref);
				$del.remove();
				updateValues();				
			}
		});
	}
	function toPounds(val){
		return parseFloat(val).toFixed(2);
	}
	function saveInvoice(){
		//store cart to session
		alert('saving...');
		return;
		/*
		var ref=$invoice.attr('action')+'/store_cart';
		var r=JQD.utils.getJsonData($invoice.serialize(),ref,'POST');
		r.done(function(o){
			if(o.message){
				var type=(o.message_type)?o.message_type:'primary';
				//JQD.utils.renderNotice({message:o.message,'type':type});
			}
		});
		*/
	}

	function updateValues(){
		var toPound=function(z){return '<strong>'+z.toFixed(2)+'</strong>';};
		$prods=$tbl_prod.find(options.input_product);

		//calculate	
		$prods=get_obj($prods);
		totals.value=0;
		totals.qty=0;
		for(var i in $prods){
			if(i){
				var q=1;
				var price=parseFloat($prods[i]);
				console.log(price);
				var lv=(price* q);
				totals.value+=lv;
				totals.qty+=q;
			}
		}
		$order_qty.html(totals.qty);
		$order_value.html(toPound(totals.value));
	}

	function get_obj($el){
		var o={};
		$.each($el.serializeArray(), function() {
			var n=this.name;
			n=n.replace('products[','');
			n=n.replace('qty[','');
			n=n.replace(']','');
			o[parseInt(n)] =this.value;
		});
		return o;
	}
	
	//public functions
	return{
		go: function(){
			init();
		},
		reset: function(){
			$tbl_prod.find(options.input_product).remove();
			updateValues();
		},
		recalc: function(){
			updateValues();
		}
	}
})(jQuery, this, this.document);
