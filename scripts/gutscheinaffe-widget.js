(function(window, $, undefined){
	
	/**
	 * Singleton AJAX request
	 */
	var SAJAX = function(options) {
		this.options	= options;
		this.id			= 0;
		this.xhr		= null;
	};
		
	// aborts old request
	SAJAX.prototype.abort = function() {
		this.id++;
		
		if ( this.xhr ) {
			this.xhr.abort();
		}
	}
	
	// sends new request and aborts old one
	SAJAX.prototype.send = function(new_options) {
		this.abort();
		
		var options = $.extend(true, {}, this.options, new_options);
		
		if ( options.success ) {
			var id = this.id;
			var self = this;
			var success = options.success;
			options.success = function(data, textStatus, jqXHR) {
				self.xhr = null;
				
				if ( id === self.id ) {
					success(data, textStatus, jqXHR);
				}
			};
		}
		
		this.xhr = $.ajax(options);
	}


	/**
	 * autocomplete
	 */
	var autocomplete = function(input, list, onSelect){
		this.input		= input;
		this.list		= list.hide();
		this.onSelect	= onSelect;
		this.cache		= {};
		this.selected   = null;
		
		this.xhr		= new SAJAX({
			url:		'http://www.gutscheinaffe.de/api/v1/',
			dataType:	'jsonp',
			data: {
				method:	'getShops',
				query:	''
			}
		});
		
		this.delay		= 300;
		this.minlength	= 2;
		
		this.bindEvents();
	};
	
	// after typing
	autocomplete.prototype.afterTyping = function() {
		if ( this.input.val().length >= this.minlength ) {
			this.getList( this.input.val() );
		}
	}
	
	// init events
	autocomplete.prototype.bindEvents = function() {
		var self = this;
		
		this.input.bind('keyup', function(event){
			self.onKeyup(event);
		});
		
		this.input.bind('blur', function(event) {
			self.list.fadeOut(self.delay);
		});

		this.input.closest('form').bind('submit', function() {
			return false;
		});
	}
	
	// on keyup
	autocomplete.prototype.onKeyup = function(event) {
		switch(event.keyCode) {
			case 38:
				if (this.selected && this.selected.length > 0) {
					this.selected.removeClass('selected');
					this.selected = this.selected.prev();
				} else {
					this.selected = this.list.find('li:last-child');
				}
				this.selected.addClass('selected');
				break;
				
			case 40:
				if (this.selected && this.selected.length > 0) {
					this.selected.removeClass('selected');
					this.selected = this.selected.next();
				} else {
					this.selected = this.list.find('li:first-child');
				}
				this.selected.addClass('selected');
				break;
				
			case 13:
				if (this.selected && this.selected.length > 0) {
					this.selected.trigger('click');
				}
				break;
				
			default:
				window.clearTimeout(this.timeout);
				
				var self = this;
				this.timeout = window.setTimeout(function(){
					self.afterTyping();
				}, this.delay);
				break;
		}
	}
	
	// request list or read from cache
	autocomplete.prototype.getList = function( query ){
		var key = query.substr(0, this.minlength);
		
		if ( this.cache[ key ] ) {
			this.showList( $.grep(this.cache[ key ], function(shop){
				return (shop.name.toLowerCase().indexOf(query.toLowerCase()) >= 0);
			}) );
		} else {
			var self = this;
			this.xhr.send( {
				data: { query: key },
				success: function(data, textStatus, jqXHR) {
					if ( data && data.success === true ) {
						self.cache[ key ] = data.result;
						self.showList( $.grep(data.result, function(shop){
							return (shop.name.toLowerCase().indexOf(query.toLowerCase()) >= 0);
						}) );
					}
				},
				cache: true
			} );
		}
	}
	
	// show list as dropdown
	autocomplete.prototype.showList = function(list) {
		var self = this;
		this.list.hide().empty();
	
		for ( var i = 0; i < list.length; i++ ) {
			$('<li><a href="javascript:void(false);">' + list[i].name + ' (' + list[i].count + ')</a></li>').bind('click', {
				shop: list[i]
			}, function(event) {
				var ret = self.onSelect(event);
				self.input.val(event.data.shop.name).trigger( 'blur' );
				return ret;
			} ).appendTo(this.list);
		}
		
		this.list.show();
	}

	
	/**
	 * widget
	 */
	var widget = function(context){
		var self		= this;
		this.context	= context;
		this.categories = $('.gutscheinaffe_widget-select',	context);
		this.table		= $('.gutscheinaffe_widget-table',	context);
		this.input		= $('.gutscheinaffe_widget-query',	context);
		this.tablecon	= $('.gutscheinaffe_widget-table-container', context);
		this.cache		= { shop: {}, category: {}, other: {} };
		
		// get config
		this.config = {};
		$('.gutscheinaffe_widget-config', context).each(function(i,el){
			el = $(el);
			self.config[ el.attr('name') ] = el.val();
		});
		
		// new autocomplete
		this.autocomplete = new autocomplete(
			this.input,
			$('.gutscheinaffe_widget-autocomplete',	context),
			function(event) { return self.onSelect(event); }
		);
		
		// standard resource for coupons
		this.xhr = new SAJAX({
			url:		'http://www.gutscheinaffe.de/api/v1/',
			dataType:	'jsonp',
			success:	function(data, statusText, jqXHR) {
				if (data && data.success === true) {
					self.showCoupons(data.result);
				}
			},
			data: {
				limit: this.config.limit
			},
			cache: true,
			beforeSend: function() {
				self.table.empty();
				self.tablecon.addClass('gutscheinaffe_widget-loading');
			},
			complete: function() {
				self.tablecon.removeClass('gutscheinaffe_widget-loading');
			}
		});
		
		this.setupCategories();
		this.performDefault();
	};
	
	widget.prototype.showCoupons = function(coupons) {
		this.table.empty();
		var html = '';
		
		for ( var i = 0; i < coupons.length; i++ ) {
			var coupon	= coupons[ i ];
			var url		= this.buildHref( coupon.url );
			
			html += '<tr>'
				 +		'<td class="gutscheinaffe_widget-logo"> <a target="_blank" rel="nofollow" href="' + url + '" title="' + coupon.title + '"><img width="88" height="33" src="' + coupon.logo + '" /></a></td>'
				 +		'<td class="gutscheinaffe_widget-worth"><a target="_blank" rel="nofollow" href="' + url + '" title="' + coupon.title + '">' + ( coupon.worth || 'Gutschein' ) + '</a></td>'
				 +		'<td class="gutscheinaffe_widget-link"> <a target="_blank" rel="nofollow" href="' + url + '" title="' + coupon.title + '">&raquo;</a></td>'
				 + '</tr>';
		}
		
		this.table.html(html);
	}
	
	// binds events for category select
	widget.prototype.setupCategories = function() {
		var self = this;
	
		// bind select event
		this.categories.bind('change', function(event) {
			var value	= $(this.options[this.selectedIndex]).val();
			var id		= parseInt(value);
			
			if (isNaN(id)) {
				if ( value != 'none' ) {
					self.xhr.send({
						data: { method: value }
					});
				}
			} else {
				self.xhr.send({
					data: {
						method: 'getByCategory',
						id:		id
					}
				});
			}
			
		});
		
	}
	
	// perform default
	widget.prototype.performDefault = function() {
		var self = this;
		
		switch (this.config['default']) {
			case 'category':
				this.categories.trigger('change');
				break;
			
			case 'shop':
				this.onSelect( { data: { shop: { id: this.config.shopid } } } );
				break;
		}
		
	}
	
	// for autocomplete selection
	widget.prototype.onSelect = function(event) {
		this.xhr.send({
			data: {
				method: 'getByShop',
				id:		event.data.shop.id
			}
		});
	}
	
	// builds encoded URL
	widget.prototype.buildHref = function( desturl ) {
		var query = {
			'a_aid':	this.config.partnerid,
			'a_bid':	'd968b18c',
			'desturl':	desturl,
			'chan':		this.config.channelid
		};
		
		var value, url = 'http://partner.gutscheinaffe.de/scripts/click.php?';
		for ( var key in query ) {
			value = query[ key ];
			
			if ( value ) {
				url += encodeURIComponent( key ) + '=' + encodeURIComponent( value ) + '&amp;';
			}
		}
		url = url.substr(0, url.length - '&amp;'.length);
		
		return url;
	}
	
	/**
	 * DOM-ready
	 */
	$(function(){
		$('.gutscheinaffe_widget').each(function(i,el){
			new widget(el);
		});
		
	});
	
})(window, jQuery);