var theList,theExtraList,toggleWithKeyboard=false,getCount,updateCount,updatePending,dashboardTotals;(function(a){setCommentsList=function(){var c,e,g,j=0,f,h,d,i,b;c=a('input[name="_total"]',"#comments-form");e=a('input[name="_per_page"]',"#comments-form");g=a('input[name="_page"]',"#comments-form");f=function(n,l){var p=a("#"+l.element),k,o,m;k=a("#replyrow");o=a("#comment_ID",k).val();m=a("#replybtn",k);if(p.is(".unapproved")){if(l.data.id==o){m.text(adminCommentsL10n.replyApprove)}p.find("div.comment_status").html("0")}else{if(l.data.id==o){m.text(adminCommentsL10n.reply)}p.find("div.comment_status").html("1")}a("span.pending-count").each(function(){var q=a(this),s,r;s=q.html().replace(/[^0-9]+/g,"");s=parseInt(s,10);if(isNaN(s)){return}r=a("#"+l.element).is("."+l.dimClass)?1:-1;s=s+r;if(s<0){s=0}q.closest("#awaiting-mod")[0==s?"addClass":"removeClass"]("count-0");updateCount(q,s);dashboardTotals()})};h=function(o,s){var u=a(o.target).attr("class"),k,l,m,r,t,q,p=false;o.data._total=c.val()||0;o.data._per_page=e.val()||0;o.data._page=g.val()||0;o.data._url=document.location.href;o.data.comment_status=a('input[name="comment_status"]',"#comments-form").val();if(u.indexOf(":trash=1")!=-1){p="trash"}else{if(u.indexOf(":spam=1")!=-1){p="spam"}}if(p){k=u.replace(/.*?comment-([0-9]+).*/,"$1");l=a("#comment-"+k);note=a("#"+p+"-undo-holder").html();l.find(".check-column :checkbox").attr("checked","");if(l.siblings("#replyrow").length&&commentReply.cid==k){commentReply.close()}if(l.is("tr")){m=l.children(":visible").length;q=a(".author strong",l).text();r=a('<tr id="undo-'+k+'" class="undo un'+p+'" style="display:none;"><td colspan="'+m+'">'+note+"</td></tr>")}else{q=a(".comment-author",l).text();r=a('<div id="undo-'+k+'" style="display:none;" class="undo un'+p+'">'+note+"</div>")}l.before(r);a("strong","#undo-"+k).text(q+" ");t=a(".undo a","#undo-"+k);t.attr("href","comment.php?action=un"+p+"comment&c="+k+"&_wpnonce="+o.data._ajax_nonce);t.attr("class","delete:the-comment-list:comment-"+k+"::un"+p+"=1 vim-z vim-destructive");a(".avatar",l).clone().prependTo("#undo-"+k+" ."+p+"-undo-inside");t.click(function(){s.wpList.del(this);a("#undo-"+k).css({backgroundColor:"#ceb"}).fadeOut(350,function(){a(this).remove();a("#comment-"+k).css("backgroundColor","").fadeIn(300,function(){a(this).show()})});return false})}return o};d=function(k,l,m){if(l<j){return}if(m){j=l}c.val(k.toString());a("span.total-type-count").each(function(){updateCount(a(this),k)})};dashboardTotals=function(q){var p=a("#dashboard_right_now"),l,o,m,k;q=q||0;if(isNaN(q)||!p.length){return}l=a("span.total-count",p);o=a("span.approved-count",p);m=getCount(l);m=m+q;k=m-getCount(a("span.pending-count",p))-getCount(a("span.spam-count",p));updateCount(l,m);updateCount(o,k)};getCount=function(k){var l=parseInt(k.html().replace(/[^0-9]+/g,""),10);if(isNaN(l)){return 0}return l};updateCount=function(l,m){var k="";if(isNaN(m)){return}m=m<1?"0":m.toString();if(m.length>3){while(m.length>3){k=thousandsSeparator+m.substr(m.length-3)+k;m=m.substr(0,m.length-3)}m=m+k}l.html(m)};updatePending=function(k){a("span.pending-count").each(function(){var l=a(this);if(k<0){k=0}l.closest("#awaiting-mod")[0==k?"addClass":"removeClass"]("count-0");updateCount(l,k);dashboardTotals()})};i=function(k,n){var q,o,u=a(n.target).parent().is("span.untrash"),m=a(n.target).parent().is("span.unspam"),t,s,l,p=a("#"+n.element).is(".unapproved");function v(r){if(a(n.target).parent().is("span."+r)){return 1}else{if(a("#"+n.element).is("."+r)){return -1}}return 0}t=v("spam");s=v("trash");if(u){s=-1}if(m){t=-1}l=getCount(a("span.pending-count").eq(0));if(a(n.target).parent().is("span.unapprove")||((u||m)&&p)){l=l+1}else{if(p){l=l-1}}updatePending(l);a("span.spam-count").each(function(){var r=a(this),w=getCount(r)+t;updateCount(r,w)});a("span.trash-count").each(function(){var r=a(this),w=getCount(r)+s;updateCount(r,w)});if(a("#dashboard_right_now").length){o=s?-1*s:0;dashboardTotals(o)}else{q=c.val()?parseInt(c.val(),10):0;q=q-t-s;if(q<0){q=0}if(("object"==typeof k)&&j<n.parsed.responses[0].supplemental.time){total_items_i18n=n.parsed.responses[0].supplemental.total_items_i18n||"";if(total_items_i18n){a(".displaying-num").text(total_items_i18n);a(".total-pages").text(n.parsed.responses[0].supplemental.total_pages_i18n);a(".tablenav-pages").find(".next-page, .last-page").toggleClass("disabled",n.parsed.responses[0].supplemental.total_pages==a(".current-page").val())}d(q,n.parsed.responses[0].supplemental.time,true)}else{d(q,k,false)}}if(!theExtraList||theExtraList.size()==0||theExtraList.children().size()==0||u||m){return}theList.get(0).wpList.add(theExtraList.children(":eq(0)").remove().clone());b()};b=function(n){var l=a.query.get(),k=a(".total-pages").text(),m=a('input[name="_per_page"]',"#comments-form").val();if(!l.paged){l.paged=1}if(l.paged>k){return}if(n){theExtraList.empty();l.number=Math.min(8,m)}else{l.number=1;l.offset=Math.min(8,m)-1}l.no_placeholder=true;l.paged++;if(true===l.comment_type){l.comment_type=""}l=a.extend(l,{action:"fetch-list",list_args:list_args,_ajax_fetch_list_nonce:a("#_ajax_fetch_list_nonce").val()});a.ajax({url:ajaxurl,global:false,dataType:"json",data:l,success:function(o){theExtraList.get(0).wpList.add(o.rows)}})};theExtraList=a("#the-extra-comment-list").wpList({alt:"",delColor:"none",addColor:"none"});theList=a("#the-comment-list").wpList({alt:"",delBefore:h,dimAfter:f,delAfter:i,addColor:"none"}).bind("wpListDelEnd",function(l,k){var m=k.element.replace(/[^0-9]+/g,"");if(k.target.className.indexOf(":trash=1")!=-1||k.target.className.indexOf(":spam=1")!=-1){a("#undo-"+m).fadeIn(300,function(){a(this).show()})}})};commentReply={cid:"",act:"",init:function(){var b=a("#replyrow");a("a.cancel",b).click(function(){return commentReply.revert()});a("a.save",b).click(function(){return commentReply.send()});a("input#author, input#author-email, input#author-url",b).keypress(function(c){if(c.which==13){commentReply.send();c.preventDefault();return false}});a("#the-comment-list .column-comment > p").dblclick(function(){commentReply.toggle(a(this).parent())});a("#doaction, #doaction2, #post-query-submit").click(function(c){if(a("#the-comment-list #replyrow").length>0){commentReply.close()}});this.comments_listing=a('#comments-form > input[name="comment_status"]').val()||""},addEvents:function(b){b.each(function(){a(this).find(".column-comment > p").dblclick(function(){commentReply.toggle(a(this).parent())})})},toggle:function(b){if(a(b).css("display")!="none"){a(b).find("a.vim-q").click()}},revert:function(){if(a("#the-comment-list #replyrow").length<1){return false}a("#replyrow").fadeOut("fast",function(){commentReply.close()});return false},close:function(){var b;if(this.cid){b=a("#comment-"+this.cid);if(this.act=="edit-comment"){b.fadeIn(300,function(){b.show()}).css("backgroundColor","")}a("#replyrow").hide();a("#com-reply").append(a("#replyrow"));a("#replycontent").val("");a("input","#edithead").val("");a(".error","#replysubmit").html("").hide();a(".waiting","#replysubmit").hide();if(a.browser.msie){a("#replycontainer, #replycontent").css("height","120px")}else{a("#replycontainer").resizable("destroy").css("height","120px")}this.cid=""}},open:function(b,d,k){var m=this,e,f,i,g,j=a("#comment-"+b),l;m.close();m.cid=b;e=a("#replyrow");f=a("#inline-"+b);i=m.act=(k=="edit")?"edit-comment":"replyto-comment";a("#action",e).val(i);a("#comment_post_ID",e).val(d);a("#comment_ID",e).val(b);if(k=="edit"){a("#author",e).val(a("div.author",f).text());a("#author-email",e).val(a("div.author-email",f).text());a("#author-url",e).val(a("div.author-url",f).text());a("#status",e).val(a("div.comment_status",f).text());a("#replycontent",e).val(a("textarea.comment",f).val());a("#edithead, #savebtn",e).show();a("#replyhead, #replybtn",e).hide();g=j.height();if(g>220){if(a.browser.msie){a("#replycontainer, #replycontent",e).height(g-105)}else{a("#replycontainer",e).height(g-105)}}j.after(e).fadeOut("fast",function(){a("#replyrow").fadeIn(300,function(){a(this).show()})})}else{l=a("#replybtn",e);a("#edithead, #savebtn",e).hide();a("#replyhead, #replybtn",e).show();j.after(e);if(j.hasClass("unapproved")){l.text(adminCommentsL10n.replyApprove)}else{l.text(adminCommentsL10n.reply)}a("#replyrow").fadeIn(300,function(){a(this).show()})}setTimeout(function(){var o,h,p,c,n;o=a("#replyrow").offset().top;h=o+a("#replyrow").height();p=window.pageYOffset||document.documentElement.scrollTop;c=document.documentElement.clientHeight||self.innerHeight||0;n=p+c;if(n-20<h){window.scroll(0,h-c+35)}else{if(o-20<p){window.scroll(0,o-35)}}a("#replycontent").focus().keyup(function(q){if(q.which==27){commentReply.revert()}})},600);return false},send:function(){var b={};a("#replysubmit .error").hide();a("#replysubmit .waiting").show();a("#replyrow input").not(":button").each(function(){b[a(this).attr("name")]=a(this).val()});b.content=a("#replycontent").val();b.id=b.comment_post_ID;b.comments_listing=this.comments_listing;b.p=a('[name="p"]').val();if(a("#comment-"+a("#comment_ID").val()).hasClass("unapproved")){b.approve_parent=1}a.ajax({type:"POST",url:ajaxurl,data:b,success:function(c){commentReply.show(c)},error:function(c){commentReply.error(c)}});return false},show:function(d){var f=this,g,i,h,e,b;f.revert();if(typeof(d)=="string"){f.error({responseText:d});return false}g=wpAjax.parseAjaxResponse(d);if(g.errors){f.error({responseText:wpAjax.broken});return false}g=g.responses[0];i=g.data;h="#comment-"+g.id;if("edit-comment"==f.act){a(h).remove()}if(g.supplemental.parent_approved){b=a("#comment-"+g.supplemental.parent_approved);updatePending(getCount(a("span.pending-count").eq(0))-1);if(this.comments_listing=="moderated"){b.animate({backgroundColor:"#CCEEBB"},400,function(){b.fadeOut()});return}}a(i).hide();a("#replyrow").after(i);h=a(h);f.addEvents(h);e=h.css("background-color");h.animate({backgroundColor:"#CCEEBB"},300).animate({backgroundColor:e},300,function(){if(b&&b.length){b.animate({backgroundColor:"#CCEEBB"},300).animate({backgroundColor:e},300).removeClass("unapproved").addClass("approved").find("div.comment_status").html("1")}})},error:function(b){var c=b.statusText;a("#replysubmit .waiting").hide();if(b.responseText){c=b.responseText.replace(/<.[^<>]*?>/g,"")}if(c){a("#replysubmit .error").html(c).show()}}};a(document).ready(function(){var e,b,c,d;setCommentsList();commentReply.init();a(document).delegate("span.delete a.delete","click",function(){return false});if(typeof QTags!="undefined"){ed_reply=new QTags("ed_reply","replycontent","replycontainer","more,fullscreen")}if(typeof a.table_hotkeys!="undefined"){e=function(f){return function(){var h,g;h="next"==f?"first":"last";g=a(".tablenav-pages ."+f+"-page:not(.disabled)");if(g.length){window.location=g[0].href.replace(/\&hotkeys_highlight_(first|last)=1/g,"")+"&hotkeys_highlight_"+h+"=1"}}};b=function(g,f){window.location=a("span.edit a",f).attr("href")};c=function(){toggleWithKeyboard=true;a("input:checkbox","#cb").click().attr("checked","");toggleWithKeyboard=false};d=function(f){return function(){var g=a('select[name="action"]');a('option[value="'+f+'"]',g).attr("selected","selected");a("#doaction").click()}};a.table_hotkeys(a("table.widefat"),["a","u","s","d","r","q","z",["e",b],["shift+x",c],["shift+a",d("approve")],["shift+s",d("spam")],["shift+d",d("delete")],["shift+t",d("trash")],["shift+z",d("untrash")],["shift+u",d("unapprove")]],{highlight_first:adminCommentsL10n.hotkeys_highlight_first,highlight_last:adminCommentsL10n.hotkeys_highlight_last,prev_page_link_cb:e("prev"),next_page_link_cb:e("next")})}})})(jQuery);