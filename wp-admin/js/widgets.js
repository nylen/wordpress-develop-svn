var wpWidgets;(function(a){wpWidgets={init:function(){var b;a("h3.sidebar-name").click(function(){var d=a(this).siblings(".widgets-sortables");if(d.is(":visible")){d.hide().sortable("disable")}else{d.show().sortable("enable").sortable("refresh")}a(this).siblings("#widget-list").toggle()});this.addEvents();a("#widget-list .widget").draggable({connectToSortable:".widgets-sortables",handle:".widget-title",distance:2,tolerance:"pointer",helper:"clone",start:function(){wpWidgets.fixWebkit(1)},stop:function(d,c){if(b){a(b).hide()}b="";wpWidgets.fixWebkit()}});a(".widgets-sortables").sortable({placeholder:"widget-placeholder",connectWith:".widgets-sortables",items:".widget",handle:".widget-title",cursor:"move",distance:2,tolerance:"pointer",opacity:0.65,start:function(d,c){wpWidgets.fixWebkit(1)},stop:function(f,c){var d=c.item.find("input.add_new").val(),i=c.item.find("input.multi_number").val(),h=c.item.attr("id"),g=a(this).parent().attr("id");if(d){if("multi"==d){c.item.html(c.item.html().replace(/<[^<>]+>/g,function(e){return e.replace(/__i__/g,i)}));c.item.attr("id",h.replace(/__i__/g,i));i++;a("li#"+h).find("input.multi_number").val(i)}else{if("single"==d){c.item.attr("id","new-"+h);b="li#"+h}}wpWidgets.addEvents(c.item);wpWidgets.save(c.item.find("form").serializeArray(),g,0);c.item.find("input.add_new").val("")}wpWidgets.saveOrder(g);wpWidgets.fixWebkit()},receive:function(d,c){if(!a(this).is(":visible")){a(this).sortable("cancel")}}}).not(":visible").sortable("disable")},saveOrder:function(c){a("#"+c+" .ajax-feedback").css("visibility","visible");var b={action:"widgets-order",savewidgets:a("#_wpnonce_widgets").val()};a(".widgets-sortables").each(function(){b[a(this).parent().attr("id")]=a(this).sortable("toArray").join(",")});a.post(ajaxurl,b,function(){a(".ajax-feedback").css("visibility","hidden")})},save:function(e,f,c,d){var b;f=f||"";a("#"+f+" .ajax-feedback").css("visibility","visible");b={action:"save-widget",savewidgets:a("#_wpnonce_widgets").val(),sidebar:f};if(c){b.delete_widget=1}a.map(e,function(h,g){b[h.name]=h.value});a.post(ajaxurl,b,function(g){var h;a(".ajax-feedback").css("visibility","hidden");if(!d){return}if(c){a(d).parents("li.widget").remove();if(!b.widget_number){h=b["widget-id"];a("#available-widgets .widget-id").each(function(){if(a(this).val()==h){a(this).parents("li.widget").show()}})}}else{a(d).parents(".widget-inside").hide()}})},fixWebkit:function(b){b=b?"none":"";a("body").css({WebkitUserSelect:b,KhtmlUserSelect:b})},addEvents:function(b){b=b||document;a("a.widget-action",b).click(function(){a(this).parents(".widget-top").siblings(".widget-inside").toggle();return false});a(".widget-control-save",b).click(function(){wpWidgets.save(a(this).parents("form").serializeArray(),a(this).parents(".widgets-holder-wrap").attr("id"),0,this);return false});a(".widget-control-remove",b).click(function(){wpWidgets.save(a(this).parents("form").serializeArray(),a(this).parents(".widgets-holder-wrap").attr("id"),1,this);return false})}};a(document).ready(function(){wpWidgets.init()})})(jQuery);