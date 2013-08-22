/*************
 * Copyright 2011 Dallan Quass
 *************/
$(function() {
   var wrTreeConfig = {
      initialOffsetX: 215,
      height: 26,
      personWidth: 126,
      personFontSize: 10,
      personMaxFontSize: 13,
      personPadding: 3,
      personTipDelay: 500,
      familyWidth: 15,
      familyFontSize: 18,
      familyPaddingTop: 3,
      familyTipDelay: 1000,
      panDistance: 200,
      zoomAmount: 200,
      minHeight: 450
   }
   $('#personNodeTemplate').template('personNodeTemplate');
   $('#personPopupTemplate').template('personPopupTemplate');
   $('#familyPopupTemplate').template('familyPopupTemplate');
   var getDataUrl = 'http://'+window.location.host+'/w/index.php?action=ajax&rs=wfGetTreeData&callback=?';
   var urlParam = function(name) {
       var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(window.location.href);
       if (!results) {
           return 0;
       }
       return results[1] || 0;
   }
   var startNodeId = decodeURIComponent(urlParam('id'));

   $jit.ST.Plot.NodeTypes.implement({
      'stroke-rect': {
        'render': function(node, canvas) {
          var width = node.getData('width'),
              height = node.getData('height'),
              align = node.getData('align'),
              pos = this.getAlignedPos(node.pos.getc(true), width, height, align),
              lineWidth = node.getData('lineWidth'),
              ctx = st.canvas.getCtx();
          ctx.save();
          ctx.lineWidth = lineWidth;
//          this.nodeHelper.rectangle.render('fill', {x: posX, y: posY}, width, height, canvas);
//          this.nodeHelper.rectangle.render('stroke', {x: posX, y: posY}, width, height, canvas);
          var sx = pos.x,
              sy = pos.y,
              ex = pos.x + width,
              ey = pos.y + height,
              r = node.getData('dim');
          var r2d = Math.PI/180;
          if( ( ex - sx ) - ( 2 * r ) < 0 ) { r = ( ( ex - sx ) / 2 ); } //ensure that the radius isn't too large for x
          if( ( ey - sy ) - ( 2 * r ) < 0 ) { r = ( ( ey - sy ) / 2 ); } //ensure that the radius isn't too large for y
          ctx.beginPath();
          ctx.moveTo(sx+r,sy);
          ctx.lineTo(ex-r,sy);
          ctx.arc(ex-r,sy+r,r,r2d*270,r2d*360,false);
          ctx.lineTo(ex,ey-r);
          ctx.arc(ex-r,ey-r,r,r2d*0,r2d*90,false);
          ctx.lineTo(sx+r,ey);
          ctx.arc(sx+r,ey-r,r,r2d*90,r2d*180,false);
          ctx.lineTo(sx,sy+r);
          ctx.arc(sx+r,sy+r,r,r2d*180,r2d*270,false);
          ctx.stroke();
          ctx.restore();
        }
      }
//      'stroke-circle': {
//        'render': function(node, canvas) {
//          var dim = node.getData('dim'),
//              pos = this.getAlignedPos(node.pos.getc(true), dim, dim, node),
//              dim2 = dim/2,
//              posX = pos.x + dim2,
//              posY = pos.y + dim2,
//              lineWidth = node.getData('lineWidth'),
//              ctx = st.canvas.getCtx();
//          ctx.save();
//          ctx.lineWidth = lineWidth;
//          this.nodeHelper.circle.render('fill', {x: posX, y: posY}, dim2, canvas);
//          this.nodeHelper.circle.render('stroke', {x: posX, y: posY}, dim2, canvas);
//          ctx.restore();
//        }
//      }
   });

   $jit.ST.Plot.EdgeTypes.implement({
     'boxy': {
       'render': function(adj, canvas) {
          var orn = this.getOrientation(adj),
              nodeFrom = adj.nodeFrom,
              nodeTo = adj.nodeTo,
              rel = nodeFrom._depth < nodeTo._depth,
              from = this.viz.geom.getEdge(rel? nodeFrom:nodeTo, 'begin', orn),
              to =  this.viz.geom.getEdge(rel? nodeTo:nodeFrom, 'end', orn),
              midX = Math.round((from.x + to.x)/2),
              fromX = Math.round(from.x),
              toX = Math.round(to.x),
              fromY = Math.round(from.y),
              toY = Math.round(to.y),
              lineWidth = adj.getData('lineWidth'),
              ctx = canvas.getCtx();
          ctx.save();
          ctx.lineWidth = lineWidth;
          ctx.beginPath();
          ctx.moveTo(fromX, fromY);
          ctx.lineTo(midX, fromY);
          ctx.lineTo(midX, toY);
          ctx.lineTo(toX, toY);
          ctx.stroke();
          ctx.restore();
       }
     }
   });

   //Create a new ST instance
   var st = new $jit.ST({
      'injectInto': 'infovis',
      duration: 400,
      transition: $jit.Trans.Quart.easeInOut,
      levelDistance: 8,
      subtreeOffset: 8,
      multitree : true,
      constrained: false,
      levelsToShow: 10,
      Navigation: {
         enable:true,
         panning:'avoid nodes',
         zooming: 50
      },
      Node: {
         height: 32,
         width: wrTreeConfig.personWidth+4,
         type: 'stroke-rect',
         color:'#FFF',
         lineWidth: 1,
         align: 'center',
         CanvasStyles: {
            fillStyle: '#FFF',
            strokeStyle: '#000', // '#23A4FF',
            lineWidth: 1
         },
         overridable: true
      },
      Edge: {
         type: 'boxy',
         lineWidth: 1,
         color: '#87B8FB', //#888888', //#23A4FF',
         overridable: true
      },

      request: function(nodeId, level, onComplete) {
         var node = st.graph.getNode(nodeId);
         var title = nodeId.substr(0, nodeId.indexOf('|'));
         var orn = node.data.$orn;
         var setRoot = '';
         if (!orn) {
            orn = node.data.oldOrn == 'left' ? 'right' : 'left';
            setRoot = '&setRoot=true';
            delete node.data.oldOrn;
         }
         $.getJSON(getDataUrl + "&orn=" + orn + "&id=" + title + setRoot, {}, function(json) {
            preprocessSubtree(json);
            onComplete.onComplete(nodeId, json);
         });
      },

      onCreateLabel: function(label, node){
         label.id = node.id;

         //set label styles
         var style = label.style;
         style.cursor = 'pointer';
         style.overflow = 'hidden';

         if (node.data.type == 'Family') {
            style.color = '#1111CC';
            style.textAlign= 'center';
            label.innerHTML = '';
         } else {
            style.color = '#333';
            style.textAlign= 'left';
            var tip = $.tmpl('personNodeTemplate', node).appendTo(label);
            handleAnchors(tip);
         }
         setLabelDimensions(label, node);

         // only click on family nodes with children that aren't root
         if (node.data.type == 'Family' && node.data.children.length && (node.data.husbandurl || node.data.wifeurl)) {
            label.onclick = function(){
               if (node.data.$orn) {
                  if (node.expanded) {
                     node.expanded = false;
                     var innerController = {
                        Move: {
                           enable: true,
                           offsetX: st.canvas.translateOffsetX + st.config.offsetX || 0,
                           offsetY: st.canvas.translateOffsetY + st.config.offsetY || 0
                        },
                        setRightLevelToShowConfig: false,
                        onBeforeRequest: function() {},
                        onBeforeContract: function() {},
                        onBeforeMove: function() {},
                        onBeforeExpand: function() {}
                     };
                     var complete = $jit.util.merge(st.controller, innerController);
                     if (!st.busy) {
                        st.busy = true;
                        st.selectPath(node, st.clickedNode);
                        st.clickedNode = node;
                        complete.onBeforeCompute(node);
                        node.eachLevel(1, false, function(n) {
                           $(st.labels.getLabel(n.id)).poshytip('destroy');
                        });
                        st.removeSubtree(node.id, false, 'animate', {
                           hideLabels: false,
                           onComplete: function() {
                              st.busy = false;
                              complete.onAfterCompute(node.id);
                              complete.onComplete();
                           }
                        });
                     }
                  }
                  else {
                     node.expanded = true;
                     st.onClick(node.id);
                  }
               }
            }
         }
         var tip = $.tmpl((node.data.type == 'Person' ? 'person' : 'family')+'PopupTemplate', node);
         if (st.root != node.id) {
            handleAnchors(tip);
            $('.popup-setroot', tip).click(function() {
               $(st.labels.getLabel(node.id)).poshytip('hide');

               // remove any node not a subnode of this node
               st.graph.eachNode(function(n) {
                  if (!n.isDescendantOf(node.id)) {
                     $(st.labels.getLabel(n.id)).poshytip('destroy');
                     st.graph.removeNode(n.id);
                  }
               });
               st.labels.clearLabels();

               // set this node as root (can't call st.graph.setRoot because it doesn't work right)
               st.root = node.id;
               node.data.$align = 'center';
               st.graph.computeLevels(node.id, 0, "ignore");
               $('.popup-setroot', tip).hide();

               // simulate a click to move the new root into place
               var tx = st.canvas.translateOffsetX;
               var ty = st.canvas.translateOffsetY;
               var sx = st.canvas.scaleOffsetX;
               var sy = st.canvas.scaleOffsetY;
               var posX = (-tx-wrTreeConfig.initialOffsetX)/sx;
               var posY = -ty/sy;
               st.config.levelsToShow = 0;
               st.onClick(node.id, {
                  Move: {
                    enable: true,
                    offsetX: -posX,
                    offsetY: -posY
                  },
                  onComplete: function() {
                     st.config.levelsToShow = 2;
                     // request more nodes
                     node.data.oldOrn = node.data.$orn;
                     delete node.data.$orn;
                     st.group.requestNodes([node], $jit.util.merge(st.controller, {
                        onComplete: function() {
                           expandFamilyNodes();
                           // show new nodes
                           st.graph.eachNode(function(n) {
                              if (!n.exist) {
                                 n.exist = true;
                                 n.drawn = true;
                                 n.visited = node.visited;
                                 setNodeStyle(n);
                              }
                           });
                           st.compute('current', false);
                           st.canvas.translate(posX, posY, true);
                           st.fx.plot(false, true);
                        }
                     }));
                  }
               });
               return false;
            });
         }
         else {
            $('.popup-setroot', tip).hide();
         }
         $('.popup-newwindow', tip).click(function() {
            window.open(node.data.url);
            return false;
         });
         $(label).poshytip({
            className: 'tip-yellow',
            bgImageFrameSize: 10,
            alignTo: 'target',
            alignX: 'center',
            offsetY: 5,
            showTimeout: (node.data.type == 'Person' ? wrTreeConfig.personTipDelay : wrTreeConfig.familyTipDelay),
            hideTimeout: 250,
            fade: false,
            slide: false,
            showOn: 'hover',
            allowTipHover: true,
            content: tip
         });
      },
      onPlaceLabel: function(label, node) {
         setLabelDimensions(label, node);
         if (node.data.type == 'Family') {
            if (node.data.children.length === 0 ||
                !(node.data.husbandurl || node.data.wifeurl) ||
                node.data.$align === 'center') {
               label.innerHTML = '&middot;';
               label.style.cursor = 'default';
            }
            else {
               label.innerHTML = (node.expanded ? '&ndash;' : '+');
               label.style.cursor = 'pointer';
            }
         }
      },

      onBeforePlotNode: function(node){
         //add some color to the nodes in the path between the
         //root node and the selected node.
         if (node.selected) {
            node.data.$lineWidth = 2;
         }
         else {
            delete node.data.$lineWidth;
         }
         setNodeStyle(node);
      },

      onBeforePlotLine: function(adj){
         if (adj.nodeFrom.selected && adj.nodeTo.selected) {
            adj.data.$lineWidth = 2;
         }
         else {
            delete adj.data.$lineWidth;
         }
      }
   });

   //load json data
   $.getJSON(getDataUrl, {id: startNodeId}, function(json) {
      preprocessSubtree(json);

      // load json data
      st.loadJSON(json);

      // compute node positions and layout
      st.compute();

      // mark all non-leaf families expanded
      expandFamilyNodes();

      //emulate a click on the root node's parent-family and grandparent-families.
      var numCalls = 0;
      st.select(st.root, {
         offsetX: wrTreeConfig.initialOffsetX,
         onComplete: function() {
            // this function is called twice
            if (numCalls++) {
               // only show two levels from now on
               st.config.levelsToShow = 2;
            }
         }
      });

      function pan(x, y) {
         var sx = st.canvas.scaleOffsetX;
         var sy = st.canvas.scaleOffsetY;
         st.canvas.translate(x * 1/sx, y * 1/sy);
      }

      function zoom(scale) {
         var val = scale / 1000,
             ans = 1 + val;
         st.canvas.scale(ans, ans);
      }

      // add event handlers for map controls
      $('#mapControlUp').click(function() {
         pan(0, wrTreeConfig.panDistance);
      });
      $('#mapControlDown').click(function() {
         pan(0, -wrTreeConfig.panDistance);
      });
      $('#mapControlLeft').click(function() {
         pan(wrTreeConfig.panDistance, 0);
      });
      $('#mapControlRight').click(function() {
         pan(-wrTreeConfig.panDistance, 0);
      });
      $('#mapControlZoomIn').click(function() {
         zoom(wrTreeConfig.zoomAmount);
      });
      $('#mapControlZoomOut').click(function() {
         zoom(-wrTreeConfig.zoomAmount);
      });
      $('#mapControlCenter').click(function() {
         var root = st.graph.getNode(st.root);
         var pos = root.getPos();
         var tx = st.canvas.translateOffsetX;
         var ty = st.canvas.translateOffsetY;
         var sx = st.canvas.scaleOffsetX;
         var sy = st.canvas.scaleOffsetY;
         st.canvas.translate(-tx/sx-pos.x-wrTreeConfig.initialOffsetX, -ty/sy-pos.y, false);
         st.canvas.scale(1/sx, 1/sy);
      });

      // add resize hook
      $(window).resize(function() {
         resizeInfovis();
      });
      // force a resize in case the size of the window changed during the above processing
      resizeInfovis();
   });

   /*********
   * Functions
   ***********/

   function resizeInfovis() {
      var infovis = $('#infovis');
      var width = infovis.width();
      var height = infovis.height();
      // don't try to fetch nodes while resizing - things get messed up
      st.config.levelsToShow = 0;
      st.canvas.resize(width, height);
      st.config.levelsToShow = 2;
   }

   function expandFamilyNodes() {
      st.graph.eachNode(function(n) {
         if (n.data.type == 'Family' && n.anySubnode()) {
            n.expanded = true;
         }
      });
   }

   function preprocessSubtree(json) {
      $jit.json.each(json, function(n) {
         n.id = n.id + '|' + Math.floor(Math.random()*2147483648);
         n.data.$align = n.data.$orn;
         if (n.data.$orn == 'center') {
            n.data.$orn = '';
         }
         if (n.data.type == 'Family') {
            n.data.$type = 'circle';
            n.data.$width = wrTreeConfig.familyWidth;
            n.data.$dim = wrTreeConfig.familyWidth;
         }
      });
   }

   function handleAnchors(tip) {
      $('.popup-anchor', tip).click(function(e) {
         if (window.parent) {
            if (e.ctrlKey) {
               window.open(this.href);
            }
            else {
               window.parent.location.href = this.href;
            }
            return false;
         }
         return true;
      });
   }

   function setLabelDimensions(label, node) {
      var style = label.style;
      var sx = st.canvas.scaleOffsetX;
      var sy = st.canvas.scaleOffsetY;

      style.height = Math.round(sy*wrTreeConfig.height)+'px';

      if (node.data.type == 'Family') {
         style.width = Math.round(sx*wrTreeConfig.familyWidth)+'px';
         style.fontSize = Math.round(sx*wrTreeConfig.familyFontSize)+'px';
         style.paddingTop = Math.round(sy*wrTreeConfig.familyPaddingTop)+'px';
      } else {
         style.width = Math.round(sx*wrTreeConfig.personWidth)+'px';
         style.fontSize = Math.min(wrTreeConfig.personMaxFontSize,Math.round(sx*wrTreeConfig.personFontSize))+'px';
         style.padding = Math.round(sx*wrTreeConfig.personPadding)+'px';
      }
   }

   function setNodeStyle(node) {
      if (node.data.type == 'Person') {
         //node.setCanvasStyle('strokeStyle', (node.data.gender == 'M' ? '#8896cc' : '#cc88b8'));
         node.setCanvasStyle('strokeStyle', (node.data.gender == 'M' ? '#87B8FB' : '#fba8f4'));
      }
   }
});