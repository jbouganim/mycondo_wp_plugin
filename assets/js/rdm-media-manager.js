var rdmVideoManager;

rdmVideoManager = (function($) {

	var self  = {
		App : null,
		Browser : null, // will be set by init()
		Inspector  : null , // will be set by Browser's initialize()
		Toolbar : null,
		Footer : null,
		ResultsCollection : null
	};

	var $container = $('#container');

	// Our basic view class
	self.view = Backbone.View.extend({
	});

	/**
	 * Video model extension class with defaults
	 */
	self.model = Backbone.Model.extend({
		defaults  : { selected: false, options: { videoID: null } }
	});

	/**
	 * Extension Collection of videos class
	 */
	self.collection = Backbone.Collection.extend({
		model: self.model,
		hasMore: false,
		page: 1,

		initialize : function() {
			this.on('change:selected', this.checkButton, this );
			this.on('change:selected', this.updateInspector, this );
			this.on('add', this.renderResults, this );
			this.on('reset', this.resetResults, this );
		},

		updateInspector : function(data) {
			if (typeof self.Inspector.update !== "undefined") {
				self.Inspector.update(data);
			}
		},

		resetResults : function(data) {
			if (typeof self.Results.render !== "undefined") {
				self.Results.close();
			}
		},

		renderResults : function(data) {
			if (typeof self.Results.render !== "undefined") {
				self.Results.insert(data);
			}
		},

		checkButton : function() {
			var toInsert = this.where({ selected: true });
			if (toInsert.length) {
				self.Footer._enableButton();
			} else {
				self.Footer._disableButton();
			}
		}
	});

	/**
	 * Main App - Two main views, browser and footer
	 */
	self.view.App = Backbone.View.extend({
		initialize : function() {
			self.Browser = new self.view.Browser();
			self.Footer = new self.view.Footer();
			this.render();
		},
		render : function() {
			$container.append( self.Browser.el, self.Footer.el );
		}
	});

	/**
	 * Browser
	 * Browser has 3 views; toolbar, inspector, results
	 */
	self.view.Browser = Backbone.View.extend({
		tagName   	: 'div',
		className 	: 'rdm-video-media-browser',
		id 			: 'browser',
		player 		: {},
		searching 	: false,

		/**
		 * Creates our browser views and runs the initial search
		 */
		initialize : function() {

			// Let's create the video collection we will be using
			self.ResultsCollection = new self.collection;

			// Create the views
			self.Toolbar = new self.view.Toolbar();
			self.Inspector = new self.view.Inspector();
			self.Results = new self.view.Results();

			this.render();

			// Do inital populate of our search
			this.searchVideos();
		},

		searchVideos : function(page) {
			if (this.$el.hasClass('loading'))
				return;

			this.page = (typeof page === "undefined") ? 1 : page;
			this.hasMore = false;
	
			this.$el.addClass('loading');
			//self.Toolbar.disable();

			var that = this;
			var args = {
				s : self.Toolbar.getTerm(),
				paged : this.page,
				posts_per_page : 10,
			};

			//AJAX Call
			$.ajax({
				url: api_endpoint + "/search/",
				type: 'GET',
				data: args,
				beforeSend: function(xhr){xhr.setRequestHeader('X-WP-Nonce', api_nonce);}
			})
			.done( function( resp ) {
				that.player = resp.player;
				// if we get results back we add it to the results list
				if (resp.posts.length) {
					that._insertVideos( resp.posts );
					if (resp.hasMore) {
						self.Results.createButton();
					} else {
						self.Results.removeButton();
					}
					that.page = args.paged;
					that.hasMore = resp.hasMore;
				} else {
					self.Results.noResults(args.s);
				}
				
			} )
			.always( function() {
				that.$el.removeClass('loading');
				//self.Toolbar.enable();
			});
		},

		_insertVideos : function(posts) {
			self.ResultsCollection.add(posts);
		},

		render : function() {
			this.$el.append( self.Toolbar.el, self.Inspector.el, self.Results.el );
			// Add the views to our DOM
			$container.append( this.$el );
		}
	});

	/**
	 * Toolbar View
	 */
	self.view.Toolbar = Backbone.View.extend({
		tagName   : 'div',
		className : 'rdm-video-media-toolbar',
		template  : _.template( $('#tmpl-rdm-video-toolbar').html() ),
		templateLoader  : _.template( $('#tmpl-rdm-video-loader').html() ),
		events    : { 
			'click button' : 'search'
		},

		initialize : function() {
			this.render();
		},

		getTerm : function () {
			return this.$el.find('input.search-box').val();
		},

		disable : function () {
			this.$el.find('input.search-box').prop("disabled", true);
		},

		enable : function () {
			this.$el.find('input.search-box').prop("disabled", false);
		},

		search : function(e) {
			self.ResultsCollection.reset();
			self.Browser.searchVideos();
		},

		render : function() {
			this.$el.html( this.template() );
			return this;
		}
	});

	/**
	 * Results View
	 */
	self.view.Results = Backbone.View.extend({
		tagName   : 'ul',
		className : 'results',
		template  : _.template( $('#tmpl-rdm-video-empty-results').html() ),
		templateLoadMore  : _.template( $('#tmpl-rdm-video-load-more').html() ),
		results : [],
		button : null,
		events    : { 
			'click button' : 'loadMore'
		},

		initialize : function() {
		},

		noResults : function(s) {
			this.$el.html( this.template( {term : s} ) );
		},

		removeButton : function() {
			this.$el.find('.load-more').remove();
		},

		loadMore : function() {
			this.removeButton();
			self.Browser.searchVideos( self.Browser.page+1 );
		},

		close : function() {
			_.each(this.results, function(result) {
				result.remove();
				result.unbind();
			});
			this.$el.html("");
		},

		createButton : function() {
			this.$el.append( this.templateLoadMore() );
		},

		insert : function(video) {
			var video = new self.view.Video({ model: video });
			this.results.push(video);
			this.$el.append( video.el );
		},

		render : function(videos) {
			var that = this;
			if (videos.length) {
				videos.each(function(video){
					var video = new self.view.Video({ model: video });
					that.$el.append( video.el );
				});
				if (videos.hasMore) {
					this.$el.append( this.templateLoadMore() );
				}
				
			} else {
				this.$el.html("");
			}	
			
		}
	});	

	/**
	 * Inspector View
	 */
	self.view.Inspector = Backbone.View.extend({
		tagName   : 'div',
		id : 'inspector',
		className : 'rdm-video-media-sidebar',
		template  : _.template( $('#tmpl-rdm-video-inspector').html() ),
		events    : { 
			'click .input-checkbox' : '_updateOptionCheckbox',
			'input .input-text' : '_updateOptionText'
		},
		initialize : function() {
		},

		clearView : function() {
			this.$el.html("<em></em>");
		},

		update : function(video) {
			this.model = video;
			this.render();
		},

		_setOption : function(key, value) {
			var options = _.clone(this.model.get('options'));
			options[ key ] = value;
			this.model.set('options', options);
		},

		_updateOptionCheckbox : function(e) {
			$(e.target).data("value", $(e.target).prop("checked") );
			this._setOption( $(e.target).data('id'), $(e.target).data('value') );
		},

		_updateOptionText : function(e) {
			$(e.target).data("value", $(e.target).val() );
			this._setOption( $(e.target).data('id'), $(e.target).data('value') );
		},

		setDefaults : function() {
			var that = this;
			this.$el.find('input').each(function(index) {
				// Set the value to the default
				$(this).data('value', $(this).data('default') );
				// Show the default value in the HTML
				if ( ($(this).attr('type') == "checkbox") && ($(this).data('value')) ) {
					$(this).prop("checked", true);
				} else {
					$(this).attr('value', $(this).data('value') );
				}
				// Set this in the option for this model
				that._setOption( $(this).data('id'), $(this).data('value') );
			});
		},

		render : function() {
			this.$el.html( this.template( this.model.toJSON() ) );
			$('.colpicker').colorpicker();
			var player = new self.view.Player( this.model.get('bcid') );
			this.$el.find('.player-container').append( player.el );

			this.setDefaults();
    		return this;
		}
	});
	

	/**
	 * Video Item
	 */
	self.view.Video = Backbone.View.extend({
		tagName   : 'li',
		className : 'result row',
		template  : _.template( $('#tmpl-rdm-video-item').html() ),
		events    : { 
			'click.result' : '_toggleSelect'
		},

		initialize : function() {
			this.render();
		},

		_toggleSelect : function(e) {
			if (this.$el.hasClass('selected')) {
				this.model.set('selected', false);
				this.$el.removeClass('selected');
				// Remove from inspector
				self.Inspector.clearView();
			} // setting
			else {
				this.model.set('selected', true);
				this.$el.addClass('selected');
				// Add this to inspector
				self.Inspector.update(this.model);
			}
		},

		render : function() {
			this.$el.html( this.template( this.model.toJSON() ) );
    		return this;
		}
	});

	/**
	 * Player
	 */
	self.view.Player = Backbone.View.extend({
		template  : _.template( $('#tmpl-rdm-video-player').html() ),

		initialize : function(videoId) {
			this.video = {
				videoId : videoId,
				player : self.Browser.player
			};
			this.render();
		},

		render : function() {
			this.$el.html( this.template( this.video ) );
    		return this;
		}
	});

	/**
	 * Footer Item
	 */
	self.view.Footer = Backbone.View.extend({
		tagName   : 'div',
		className : 'rdm-video-media-bottom-sidebar',
		template  : _.template( $('#tmpl-rdm-video-footer').html() ),
		events    : { 
			'click button' : '_insert',
		},

		initialize : function() {
			this.render();
			this._disableButton();
		},

		_insert : function(e) {
			var that = this;
			e.preventDefault();
			var shortcodes = '';
			var toInsert = self.ResultsCollection.where({ selected: true });
			_.each(toInsert, function(video) {
				shortcodes += that.createShortcode(video) + "\n";
			});
			if (typeof window.parent.send_to_editor !== "undefined") {
				window.parent.send_to_editor( shortcodes );
			}
		},

		createShortcode : function(model) {
			var shortcode = (typeof video_shortcode !== "undefined") ? video_shortcode : 'video';
			var html = '[' + shortcode;
			var options = model.get('options');
			// Add the bcid as one of our options
			options['videoID'] = model.get('bcid');
			// Merge all the options
			for (var prop in options) {
				if (options[prop] !== "") {
					html += " " + prop + '="' + options[prop] + '"';
				}
			}
			html += ']';
			return html;
		},

		_disableButton : function() {
			this.$el.find('button').prop( 'disabled', 'disabled' );
		},

		_enableButton : function() {
			this.$el.find('button').removeAttr( 'disabled' );
		},

		render : function(data) {
			this.$el.html( this.template() );
    		return this;
		}
	});


	/**
	 * Init
	 */
	self.init = function() {
		if (typeof api_endpoint === "undefined")
			return;

		self.App = new self.view.App();
	};

	return self;

}(jQuery));

rdmVideoManager.init();