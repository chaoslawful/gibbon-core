/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

jQuery(function($){

    var markbookResizeObserver = null;

    function layoutMarkbookGroupLabels() {
        var $container = $('.doublescroll-container');
        var $labels = $('#myTable.markbook th.markbookGroupHeaderCell .marksColumnGroupLabel');
        if (!$container.length || !$labels.length) {
            return;
        }

        var containerRect = $container[0].getBoundingClientRect();

        $labels.each(function () {
            var $label = $(this);
            var $th = $label.closest('th.markbookGroupHeaderCell');
            if (!$th.length) {
                return;
            }

            var thRect = $th[0].getBoundingClientRect();
            var visibleLeft = Math.max(thRect.left, containerRect.left);
            var visibleRight = Math.min(thRect.right, containerRect.right);
            var visibleWidth = Math.max(0, visibleRight - visibleLeft);

            if (visibleWidth <= 0) {
                $label.css({ visibility: 'hidden', width: 0, left: 0 });
                return;
            }

            // Pin title to the left edge of the visible slice of this group header
            var left = Math.max(0, visibleLeft - thRect.left);
            $label.css({
                visibility: 'visible',
                left: left + 'px',
                width: visibleWidth + 'px',
            });
        });
    }

    function syncMarkbookStickyColumn() {
        var $table = $('#myTable.markbook');
        if (!$table.length) return;

        var $container = $table.closest('.doublescroll-container');
        var isGrouped = $container.hasClass('markbookGrouped') || $table.hasClass('markbookGrouped');
        var groupHeaderHeight = isGrouped ? ($table.find('thead tr.markbookGroupHeaderRow').outerHeight() || 24) : 0;

        var $headRow = $table.find('thead tr.head');
        var $headFirst = $headRow.find('th.firstColumn');
        // Prefer marks columns; dataColumn target/baseline can be shorter in CSS
        var $headMeasure = $headRow.find('th.marksColumn').first();
        if (!$headMeasure.length) {
            $headMeasure = $headRow.find('th.dataColumn').first();
        }
        if ($headFirst.length && $headMeasure.length) {
            var headHeight = $headMeasure.outerHeight();
            $headFirst.css({
                height: headHeight + 'px',
                top: groupHeaderHeight + 'px',
            });
            $headFirst.children('span').css({
                height: headHeight + 'px',
                display: 'table-cell',
                verticalAlign: 'middle',
            });
        }

        var $groupSpacer = $table.find('thead tr.markbookGroupHeaderRow th.firstColumn');
        if ($groupSpacer.length) {
            $groupSpacer.css({
                height: groupHeaderHeight + 'px',
                top: '0px',
            });
        }

        $table.find('tbody tr').each(function () {
            var $row = $(this);
            var $first = $row.children('td.firstColumn');
            if (!$first.length) return;

            // Prefer a marks data cell; fall back to summary dataColumn
            var $measure = $row.children('td.columnLabel').first();
            if (!$measure.length) {
                $measure = $row.children('td.dataColumn').not('.studentTarget').first();
            }
            if (!$measure.length) {
                $measure = $row.children('td.dataColumn').first();
            }
            if (!$measure.length) return;

            // Use the row's in-flow cell height (border box) so absolute student cell matches
            var rowHeight = $measure.outerHeight();
            $first.css('height', rowHeight + 'px');
        });

        // Keep top scrollbar placeholder in sync with final table width
        var tableWidth = $table.outerWidth();
        if (tableWidth) {
            $('.doublescroll-top-tablewidth').width(tableWidth);
        }

        layoutMarkbookGroupLabels();
    }

    function scheduleMarkbookLayoutSync() {
        syncMarkbookStickyColumn();

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                syncMarkbookStickyColumn();
                window.requestAnimationFrame(syncMarkbookStickyColumn);
            });
        }

        // Icons/fonts can settle after first paint; retry a few times
        setTimeout(syncMarkbookStickyColumn, 0);
        setTimeout(syncMarkbookStickyColumn, 50);
        setTimeout(syncMarkbookStickyColumn, 150);
        setTimeout(syncMarkbookStickyColumn, 400);
        setTimeout(syncMarkbookStickyColumn, 1000);

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                syncMarkbookStickyColumn();
            });
        }
    }

    function bindMarkbookLayoutObservers() {
        var table = document.getElementById('myTable');
        if (!table || typeof ResizeObserver === 'undefined') {
            return;
        }

        if (markbookResizeObserver) {
            markbookResizeObserver.disconnect();
            markbookResizeObserver = null;
        }

        var pending = false;
        markbookResizeObserver = new ResizeObserver(function () {
            if (pending) return;
            pending = true;
            window.requestAnimationFrame(function () {
                pending = false;
                syncMarkbookStickyColumn();
            });
        });
        markbookResizeObserver.observe(table);

        // Nested column label tables / icon swaps can change row height
        table.querySelectorAll('tbody tr').forEach(function (row) {
            markbookResizeObserver.observe(row);
        });
    }

    function bindMarkbookScrollSync() {
        var $container = $('.doublescroll-container');
        var $top = $('.doublescroll-top');

        // Namespaced handlers so HTMX re-init can replace bindings on new nodes
        $top.off('scroll.markbook').on('scroll.markbook', function () {
            $container.scrollLeft($top.scrollLeft());
            layoutMarkbookGroupLabels();
        });
        $container.off('scroll.markbook').on('scroll.markbook', function () {
            $top.scrollLeft($container.scrollLeft());
            layoutMarkbookGroupLabels();
        });
    }

    function scrollMarkbookToRecentColumns() {
        var $table = $('#myTable.markbook');
        var $container = $('.doublescroll-container');
        if (!$table.length || !$container.length) {
            return;
        }

        var tableWidth = $table.outerWidth() || $table.width();
        $('.doublescroll-top-tablewidth').width(tableWidth);
        // Programmatic scrollLeft often does not fire 'scroll'; update labels explicitly
        $container.scrollLeft(tableWidth);
        $('.doublescroll-top').scrollLeft(tableWidth);
        layoutMarkbookGroupLabels();
    }

    function initMarkbookDragtable() {
        var $table = $('#myTable.markbook');
        if (!$table.length || !$table.find('.dragtable-drag-handle').length) {
            return;
        }
        // New DOM after HTMX swap — always bind once on this element
        if ($table.data('jb-dragtable')) {
            return;
        }
        $table.dragtable({
            items: 'thead th .dragtable-drag-handle',
            scroll: true,
            appendTarget: ':parent',
        });
    }

    /**
     * Full markbook view bootstrap. Safe to call after HTMX content swaps
     * (filter form uses enableQuickSubmit → replaces #content-wrap without reload).
     */
    function initMarkbookView() {
        if (!$('#myTable.markbook').length) {
            return;
        }

        bindMarkbookScrollSync();
        bindMarkbookLayoutObservers();
        initMarkbookDragtable();
        scrollMarkbookToRecentColumns();
        scheduleMarkbookLayoutSync();
    }

    function contentContainsMarkbook(content) {
        if (!content) {
            return false;
        }
        if (content.id === 'myTable' || (content.classList && content.classList.contains('doublescroll-container'))) {
            return true;
        }
        if (typeof content.querySelector === 'function') {
            return !!content.querySelector('#myTable.markbook, .doublescroll-container, .markbookGroupHeaderRow');
        }
        return false;
    }

    // First full page load
    initMarkbookView();

    $(window).on('load', function () {
        scrollMarkbookToRecentColumns();
        scheduleMarkbookLayoutSync();
    });

    // If this script runs after window load (cached nav), still init scroll position
    if (document.readyState === 'complete') {
        scrollMarkbookToRecentColumns();
        scheduleMarkbookLayoutSync();
    }

    $(window).on('resize.markbook', function () {
        scheduleMarkbookLayoutSync();
    });

    // Term / filter submit uses HTMX quick-submit — re-bind after #content-wrap swap
    if (typeof htmx !== 'undefined') {
        htmx.onLoad(function (content) {
            if (contentContainsMarkbook(content)) {
                initMarkbookView();
            }
        });
    }
    document.body.addEventListener('htmx:afterSettle', function (evt) {
        var target = evt.detail && evt.detail.target;
        if (contentContainsMarkbook(target) || (target && target.querySelector && target.querySelector('#myTable.markbook'))) {
            initMarkbookView();
        } else if (document.querySelector('#myTable.markbook') && target && target.id === 'content-wrap') {
            initMarkbookView();
        }
    });
    // In markbook_edit_data.php, update the attainment value to match raw score
    // But not the other way around, in case teachers need to adjust the value
	$('input[id$="attainmentValueRaw"]').change( function() {

        var attainmentRawMax = $('[name="attainmentRawMax"]');
		// This value wont exist if not using a percent scale
		if (attainmentRawMax.length == false) return;

		$(this).removeClass('highlight');
        $(this).prop('title', '' );

		var index = $(this).attr('name').substr(0, $(this).attr('name').indexOf('-'));
		var thisValue = parseFloat( $(this).val() );
		var maxValue = parseFloat( attainmentRawMax.val() );
        var attainment = $( '#' + index + '-attainmentValue');
        var scaleType = $('[name="attainmentScaleType"]').val();

		if ( $(this).val() == '' || maxValue == 0 || maxValue == '') {
			return;
		}
		else if ( isNaN(thisValue) ) {
			attainment.find('option[value=""]').prop('selected', true);
			$(this).val('');
			return;
        }
        
		if (scaleType == '%') {
			if ( thisValue > maxValue ) {
				thisValue = maxValue;
				$(this).val(thisValue);
			}

			var calculatedValue = Math.round( ( thisValue / maxValue ) * 100  );

			if (calculatedValue >= 0 && calculatedValue <= 100) {
				if (attainment.val( calculatedValue + '%' ).length) {
					attainment.val( calculatedValue + '%' ).prop('selected', true);
				} else {
					attainment.find('option[value=""]').prop('selected', true);
					$(this).addClass('highlight');
				}
			}
		}
	});

	// Highlight a raw value if it doesnt match the percent, but don't change it
	$('select[id$="attainmentValue"]').change( function() {

        var attainmentRawMax = $('[name="attainmentRawMax"]');
        var scaleType = $('[name="attainmentScaleType"]').val();

		// This value wont exist if not using a percent scale
		if (attainmentRawMax.length == false || scaleType != '%') return;

		var index = $(this).attr('name').substr(0, $(this).attr('name').indexOf('-'));
		var attainmentRaw = $( '#' + index + '-attainmentValueRaw');

		if (attainmentRaw.length) {
			attainmentRaw.removeClass('highlight');

			if (attainmentRaw.val() != '' ) {
				var rawValue = parseFloat( attainmentRaw.val() );
				var maxValue = parseInt( attainmentRawMax.val() );

				var calculatedValue = Math.round( ( rawValue / maxValue ) * 100  ) ;
				if (calculatedValue >= 0 && calculatedValue <= 100 && calculatedValue+'%' != $(this).val() ) {
					attainmentRaw.addClass('highlight');
                    attainmentRaw.prop('title', calculatedValue+'%' );
				}
			}
		}
	});

});



/*!
 * dragtable - jquery ui widget to re-order table columns
 * version 3.0
 *
 * Copyright (c) 2010, Jesse Baird <jebaird@gmail.com>
 * 12/2/2010
 * https://github.com/jebaird/dragtable
 *
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 *
 *
 * Forked from https://github.com/akottr/dragtable - Andres Koetter akottr@gmail.com
 *
 *
 *
 *
 * quick down and and dirty on how this works
 * ###########################################
 * so when a column is selected we grab all of the cells in that row and clone append them to a semi copy of the parent table and the
 * "real" cells get a place holder class witch is removed when the dragstop event is triggered
 *
 *
 * make it easy to have a button swap columns
 *
 *
 * Events - in order of trigger
 * start - when the user mouses down on handle or th, use in favor of display helper
 * beforeChange - called when a col will be moved
 * change - called after the col has been moved
 * stop - the user mouses up and stops dragging and the drag display is removed from the dom
 *
 *
 *
 *
 * IE notes
 *  ie8 in quirks mode will only drag once after that the events are lost
 *
 */

 (function($) {
  $.widget("jb.dragtable", {
        //TODO: implement this
        eventWidgetPrefix: 'dragtable',
        options: {
            //used to the col headers, data contained in here is used to set / get the name of the col
            dataHeader:'data-header',
            //class name that handles have
            handle:'dragtable-drag-handle',
            //draggable items in cols, .dragtable-drag-handle has to match the handle options
            items: 'th:not( :has( .dragtable-drag-handle ) ), .dragtable-drag-handle',
            //if a col header as this class, cols cant be dragged past it
            boundary: 'dragtable-drag-boundary',
            //classnames that get applied to the real td, th
            placeholder: 'dragtable-col-placeholder',

            /*
                the drag display will be appended to this element,
                if this is set to  document.body and has been zeroed off the display will seem to jump

                Its either : parent or a dom node
            */
            appendTarget: ':parent',
            //if true,this will scroll the appendTarget offsetParent when the dragDisplay is dragged past its boundaries
            scroll: false

        },
        // when a col is dragged use this to find the semantic elements, for speed
        tableElemIndex:{
            head: '0',
            body: '1',
            foot: '2'
        },
        tbodyRegex: /(tbody|TBODY)/,
        theadRegex: /(thead|THEAD)/,
        tfootRegex: /(tfoot|TFOOT)/,

        _create: function() {

            //console.log(this);
            //used start/end of drag
            this.startIndex = null;
            this.endIndex = null;
            //the references to the table cells that are getting dragged
            this.currentColumnCollection = [];
            //the references the position of the first element in the currentColunmCollection position
            this.currentColumnCollectionOffset = {};
            //the div wrapping the drag display table
            this.dragDisplay = $([])


            var self = this,
                o = self.options,
                el = self.element;

            o.appendTarget = ( o.appendTarget === ':parent' ) ? this.element.parent() : $( o.appendTarget );


            //grab the ths and the handles and bind them
            el.delegate(o.items, 'mousedown.' + self.widgetEventPrefix, function(e){

                var $handle = $(this),
                    elementOffsetTop = self.element.position().top;

                //make sure we are working with a th instead of a handle
                if( $handle.hasClass( o.handle ) ){

                    $handle = $handle.closest('th');
                    //change the target to the th, so the handler can pick up the offsetleft
                    e.currentTarget = $handle.closest('th')[0]
                }


                self.getCol( $handle.index() )
                    .attr( 'tabindex', -1 )
                    .focus()
                    .disableSelection()
                    .css({
                        top: elementOffsetTop,
                            //need to account for the scroll left of the append target, other wise the display will be off by that many pix
                            left: ( self.currentColumnCollectionOffset.left + 0 ) // o.appendTarget[0].scrollLeft -- removed SK
                        })
                    .appendTo( o.appendTarget )



                self._mousemoveHandler( e );
                //############
            });

        },

        /*
         * e.currentTarget is used for figuring out offsetLeft
         * getCol must be called before this is
         *
         */
         _mousemoveHandler: function( e ){
            //call this first, catch any drag display issues
            this._start( e )

            var self = this,
                o = self.options,
                prevMouseX = e.pageX,
                dragDisplayWidth = self.dragDisplay.outerWidth(),
                halfDragDisplayWidth = dragDisplayWidth / 2,
                appendTargetOP = o.appendTarget.offsetParent()[0],
                scroll = o.scroll,

                //get the col count, used to contain col swap
                colCount = self.element[ 0 ]
                    .getElementsByTagName( 'thead' )[ 0 ]
                    .getElementsByTagName( 'tr' )[ 0 ]
                    .getElementsByTagName( 'th' )
                    .length - 1;


                $( document ).bind('mousemove.' + self.widgetEventPrefix, function( e ){
                    var columnPos = self._setCurrentColumnCollectionOffset(),
                        mouseXDiff = e.pageX - prevMouseX,
                        appendTarget = o.appendTarget[0],
                        left =  ( parseInt( self.dragDisplay[0].style.left ) + mouseXDiff  );
                        self.dragDisplay.css( 'left', left )

               /*
                * when moving left and e.pageX and prevMouseX are the same it will trigger right when moving left
                *
                * it should only swap cols when the col dragging is half over the prev/next col
                */
                if( e.pageX  < prevMouseX ){
                   //move left
                   var threshold = columnPos.left - halfDragDisplayWidth;


                   //scroll left
                   if( left < ( appendTarget.clientWidth  - dragDisplayWidth ) && scroll == true ) {
                    var scrollLeft =  appendTarget.scrollLeft + mouseXDiff
                        /*
                         * firefox does scroll the body with target being body but chome does
                         */
                         if( appendTarget.tagName == 'BODY' ) {
                            window.scroll( window.scrollX + scrollLeft, window.scrollY );
                        } else {
                            appendTarget.scrollLeft = scrollLeft;
                        }

                    }


                    if( left  < threshold ){
                        self._swapCol(self.startIndex-1);
                    }

                }else{
                 //move right
                 var threshold = columnPos.left + halfDragDisplayWidth ;

                    //scroll right
                    if( left > (appendTarget.clientWidth - dragDisplayWidth ) && scroll == true ) {
                        //console.log(  o.appendTarget[0].clientWidth + (e.pageX - prevMouseX))

                        var scrollLeft =  appendTarget.scrollLeft + mouseXDiff
                        /*
                         * firefox does scroll the body with target being body but chome does
                         */
                         if( appendTarget.tagName == 'BODY' ) {
                            window.scroll( window.scrollX + scrollLeft, window.scrollY );
                        } else {
                            appendTarget.scrollLeft = scrollLeft;
                        }

                    }

                    //move to the right only if x is greater than threshold and the current col isn' the last one
                    if( left  > threshold  && colCount != self.startIndex ){
                        self._swapCol( self.startIndex + 1 );
                    }
                }
                //update mouse position
                prevMouseX = e.pageX;

            })
            .one( 'mouseup.' + self.widgetEventPrefix ,function(e ){
                self._stop( e );
            });

            },

            _start: function( e ){

                $( document )
                    //move disableselection and cursor to default handlers of the start event
                    .disableSelection()
                    .css( 'cursor', 'move');

                    // guess the width of the column that is getting dragged and apply it to the drag display. fixes issues with
                    // cols / tables having fixed widths
                    this.dragDisplay.width( this.currentColumnCollection[0].clientWidth )

                    return this._eventHelper('start',e);

                },
                _stop: function( e ){

                    // issue #25 reorder the stop event order always remove the stop event
                    $( document )
                        .unbind( 'mousemove.' + this.widgetEventPrefix )
                        .enableSelection()
                        .css( 'cursor', '');

                    // clean up
                    this
                        .dropCol()
                        .dragDisplay.remove();
                    // let the world know we have stopped
                    this._eventHelper('stop',e,{});


               },

               _setOption: function(option, value) {
                $.Widget.prototype._setOption.apply( this, arguments );

            },

        /*
         * get the selected index cell out of table row
         * needs to work as fast as possible. and performance gains in this method are worth the time
         *  because its used to build the drag display and get the cells on col swap
         * http://jsperf.com/binary-regex-vs-string-equality/4
         */
         _getCells: function( elem, index ){
            //console.time('getcells');
            var td,
                parentNodeName,
                ei = this.tableElemIndex,
                //TODO: clean up this format
                tds = {
                    //store where the cells came from
                    'semantic':{
                        '0': [],//head throws error if ei.head or ei['head']
                        '1': [],//body
                        '2': []//footer
                    },
                    //keep a ref in a flat array for easy access
                    'array':[]
                },
                //cache regex, reduces looking up the chain
                theadRegex = this.theadRegex,
                tbodyRegex = this.tbodyRegex,
                tfootRegex = this.tfootRegex,


                tdsSemanticHead = tds.semantic[ei.head],
                tdsSemanticBody = tds.semantic[ei.body],
                tdsSemanticFoot = tds.semantic[ei.foot];

            //console.log(index);
            //check does this col exsist
            if(index <= -1 || typeof elem.rows[0].cells[index] == undefined){
                return tds;
            }

            for(var i = 0, length = elem.rows.length; i < length; i++){

                td = elem.rows[i].cells[index];

                //if the row has no cells dont error out;
                if( td == undefined ){
                    continue;
                }

                parentNodeName = td.parentNode.parentNode.nodeName;
                tds.array.push(td);
                //faster to leave out ^ and $ in the regular expression
                if( tbodyRegex.test( parentNodeName ) ){

                    tdsSemanticBody.push( td );

                }else if( theadRegex.test( parentNodeName ) ){

                    tdsSemanticHead.push( td );

                }else if( this.tfootRegex.test( parentNodeName ) ){

                    tdsSemanticFoot.push( td );
                }


            }

            return tds;
        },
        /*
         * returns all element attrs in a string key="value" key2="value"
         */
         _getElementAttributes: function(element){

            var attrsString = [],
                attrs = element.attributes,
                i = 0,
                length = attrs.length;

            for( ; i < length; i++) {
                attrsString.push( attrs[i].nodeName + '="' + attrs[i].value+'"' );
            }
            return attrsString.join(' ');
        },

        /*
         * faster than swap nodes
         * only works if a b parent are the same, works great for columns
         */
         _swapCells: function(a, b) {
            a.parentNode.insertBefore(b, a);
        },

        /*
         * used to trigger optional events
         */
         _eventHelper: function(eventName ,eventObj, additionalData){
            return this._trigger(
                eventName,
                eventObj,
                $.extend({
                    column: this.currentColumnCollection,
                    order: this.order(),
                    startIndex: this.startIndex,
                    endIndex: this.endIndex,
                    dragDisplay: this.dragDisplay,
                    columnOffset: this.currentColumnCollectionOffset
                },additionalData)
                );
        },
        /*
         * build copy of table and attach the selected col to it, also removes the select col out of the table
         * @returns copy of table with the selected col
         *
         * populates self.dragDisplay
         * TODO: name this something better, like select col or get dragDisplay
         *
         */
        getCol: function(index){
            //console.log('index of col '+index);
            //drag display is just simple html

            var target,
                cells,
                clone,
                tr,
                i,
                length,
                $table = this.element,
                self = this,
                eIndex = self.tableElemIndex,
                placholderClassnames = ' ' + this.options.placeholder;;

                //BUG: IE thinks that this table is disabled, dont know how that happend
            self.dragDisplay = $('<table '+self._getElementAttributes($table[0])+'></table>')
                .addClass('dragtable-drag-col');

            //start and end are the same to start out with
            self.startIndex = self.endIndex = index;


            cells = self._getCells($table[0], index);
            self.currentColumnCollection = cells.array;

            //################################

            //TODO: convert to for in // its faster than each
            $.each(cells.semantic,function(k,collection){
                //dont bother processing if there is nothing here

                if(collection.length == 0){
                    return;
                }

                if ( k == '0' ){
                    target = document.createElement('thead');
                    self.dragDisplay[0].appendChild(target);

                }else if ( k == 1 ) {
                    target = document.createElement('tbody');
                    self.dragDisplay[0].appendChild(target);

                }else {
                    target = document.createElement('tfoot');
                    self.dragDisplay[0].appendChild(target);
                }

                for(i = 0,length = collection.length; i < length; i++){

                    clone = collection[i].cloneNode(true);
                    collection[i].className+=placholderClassnames;
                    tr = document.createElement('tr');
                    tr.appendChild(clone);

                    target.appendChild(tr);

                }
            });


            this._setCurrentColumnCollectionOffset();


            self.dragDisplay  = $('<div class="dragtable-drag-wrapper"></div>').append(self.dragDisplay)
            return self.dragDisplay;
        },


        _setCurrentColumnCollectionOffset: function(){
            return this.currentColumnCollectionOffset = $( this.currentColumnCollection[0] ).position();
        },

        /*
         * move column left or right
         */
         _swapCol: function( to ){

            //cant swap if same position
            if(to == this.startIndex){
                return false;
            }

            var from = this.startIndex;
            this.endIndex = to;
            //this col cant be moved past me
            var th = this.element.find('th:not(.columnLabel)').eq( to );
            //alert( th.data('header') );
            //check on th
            if( th.hasClass( this.options.boundary ) == true ){
                return false;
            }
            //check handle element
            if( th.find( '.' + this.options.handle ).hasClass( this.options.boundary ) == true ){
                return false;
            }

            if( this._eventHelper('beforeChange',{}) === false ){
                return false;
            };

            if(from < to) {
                //console.log('move right');
                for(var i = from; i < to; i++) {
                        var row2 = this._getCells(this.element[0],i+1);
                    //console.log(row2)
                    for(var j = 0, length = row2.array.length; j < length; j++){
                        this._swapCells(this.currentColumnCollection[j],row2.array[j]);
                    }
                }
            } else {
                //console.log('move left');
                for(var i = from; i > to; i--) {
                    var row2 = this._getCells(this.element[0],i-1);
                    for(var j = 0, length = row2.array.length; j < length; j++){
                        this._swapCells(row2.array[j],this.currentColumnCollection[j]);
                    }
                }
            }
            this._eventHelper('change',{});

            this.startIndex = this.endIndex;
        },
        /*
         * called when drag start is finished
         */
         dropCol: function(){
            //TODO: cache this when the option is set
            var regex = new RegExp("(?:^|\\s)" + this.options.placeholder + "(?!\\S)",'g');
            //remove placeholder class
            //dont use jquery.fn.removeClass for performance reasons
            for(var i = 0, length = this.currentColumnCollection.length; i < length; i++){
                var td = this.currentColumnCollection[i];

                td.className = td.className.replace(regex,'')
            }

            return this;

        },
        /*
         * get / set the current order of the cols
         */
         order: function(order){
            var self = this,
                elem = self.element,
                options = self.options,
                headers = elem.find('thead tr:first').children('th');


            if(order == undefined){
                //get
                var ret = [];
                headers.each(function(){
                    var header = this.getAttribute(options.dataHeader);
                    if(header == null){
                        //the attr is missing so grab the text and use that
                        header = $(this).text();
                    }

                    ret.push(header);

                });

                return ret;

            }else{
                //set
                //headers and order have to match up
                if(order.length != headers.length){

                    return self;
                }
                for(var i = 0, length = order.length; i < length; i++){

                   var start = headers.filter('['+ options.dataHeader +'='+ order[i] +']').index();
                   if(start != -1){

                        self.startIndex = start;

                        self.currentColumnCollection = self._getCells(self.element[0], start).array;

                        self._swapCol(i);
                    }


                }
                return self;
            }
        },

        destroy: function() {
            var self = this,
                o = self.options;

            this.element.undelegate( o.items, 'mousedown.' + self.widgetEventPrefix );

            $( document ).unbind('.' + self.widgetEventPrefix )

        }


    });

})(jQuery);
