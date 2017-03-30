// 最初に表示するページ
// var default_paper = "C02-1045"; index.php で指定する

// 表示中の論文とページ
var current_paper = null;
var current_page = 0;

// 起動時の初期設定
$(document).ready(function() {

    // 画面レイアウトをブラウザの大きさに合わせて変更
    resetLayout();

    // 論文 XHTML を表示
    showPaperXhtml(default_paper);

    // 論文 PDF 画像 を表示
    showPaperImage(default_paper, 1);
    
    current_paper = default_paper;
    current_page = 1;

    // 論文セレクタの操作
    $("#paper_select").change(function() {
	var new_paper = $(this).val();
	if (new_paper != current_paper) {
	    showPaperXhtml(new_paper);
	    showPaperImage(new_paper, 1);
	    current_paper = new_paper;
	    current_page = 1;
	}
    });
    $("#paper_select_input_button").click(function() {
	var new_paper = $("#paper_select_input").val();
	if (new_paper != current_paper) {
	    showPaperXhtml(new_paper);
	    showPaperImage(new_paper, 1);
	    current_paper = new_paper;
	    current_page = 1;
	}
    });

    // ブラウザサイズを変更した場合のイベントハンドラ
    var resizeTimer = false;
    $(window).resize(function() {
	if (resizeTimer !== false) {
	    clearTimeout(resizeTimer);
	}
	resizeTimer = setTimeout(function() {
	    resetLayout();
	}, 200);
    });

});

// 画面レイアウトを画面サイズに合わせて変更
function resetLayout() {
    var w = $(window).width();
    var h = $(window).height();

    // 高さを決定
    var content_height = h - 35; // セレクタの分を引く
    $("#iframe_xhtml").height(content_height);
    $("#paper").height(content_height);

    // 幅を決定
    var content_width = (w - 10) / 2;
    $("#iframe_xhtml").width(content_width);
    $("#paper").width(content_width);
}

// XHTML を iframe に読み込む
function showPaperXhtml(code) {
    // XHTML を表示
    var url = "xhtml/" + code + ".xhtml";
    var jo = $("#iframe_xhtml")[0];
    if (jo) {
	$("#iframe_xhtml").on('load', function() {
	    // var o = $("#iframe_xhtml").contents().find("div.box[data-name='Title']>p");
	    assignActions();
	});
	jo.contentDocument.location.replace(url);
    }

    return;
}

// ページ画像を表示する
// 先頭ページは 1
function showPaperImage(code, page) {
    if (code != current_paper || page != current_page) {
	var image_url = "xhtml/images/" + code + "/" + code + "-" + ('0' + page).slice(-2) + '.png';
	$("#paper_image").attr("src", image_url);
	current_page = page;
    }
}

// xhtml から n ページ目の幅と高さの情報を取得
var pages;
function getPageInfo(n) {
    pages = $("#iframe_xhtml").contents().find("pages>page");
    page = pages.eq(n);
    info = {};
    info.width = parseFloat(page.attr('width')) * 100; // 100 dpi
    info.height = parseFloat(page.attr('height')) * 100;
    return info;
}

// イベントアクションをセット
function assignActions() {
    // マウスオーバー時にボックスを表示
    $("#iframe_xhtml").contents().find("p").hover(
	function() {
	    var p_text = $(this).attr('id');
	    
	    // $(this).parents("div.section").css("border", "1px solid #FF0000");
	    var section_label = $(this).parents("div.section").attr("data-name");
	    var box_label = $(this).parents("div.box").attr("data-name");
	    
	    page = parseInt($(this).attr("data-page"));
	    var str_bdr = $(this).attr("data-bdr");
	    var bdr = str_bdr.split(',');
	    if (bdr.length != 4) return false;
	    showPaperImage(current_paper, page + 1);

	    var pageInfo = getPageInfo(page);
	    var l = parseFloat(bdr[0]) * pageInfo.width - 4; // 8.2677 inch * 100dpi
	    var t = parseFloat(bdr[1]) * pageInfo.height - 4;// 11.6929 inch * 100dpi
	    var w = parseFloat(bdr[2]) * pageInfo.width - l;
	    var h = parseFloat(bdr[3]) * pageInfo.height - t;
	    var pid = $(this).attr("id");
	    var box = '<div class="boxlabel" style="left:' + l.toString() + 'px;top:' + (t - 20).toString() + 'px;">' + section_label + ':' + box_label + ' [' + p_text + ']</div>';
	    box = box + '<div class="box" data-page="' + (page + 1).toString() + '" style="left:' + l.toString() + 'px;top:' + t.toString() + 'px;width:' + w.toString() + 'px;height:' + h.toString() + 'px;" />';
	    $("#paper").remove("#div.box, #div.boxlabel");
	    $("#paper").append(box);
	    $("#paper div.box").show();

	},
	function() {
	    // $(this).parents("div.section").css("border", "1px solid #FFFFFF");
	    $("#paper div.box").remove();
	    $("#paper div.boxlabel").remove();
	}
    );
    $("#iframe_xhtml").contents().find("span.word").hover(
	function() {
	    var str_bdr = $(this).attr("data-bdr");
	    var bdr = str_bdr.split(',');
	    if (bdr.length != 4) return false;
	    var page = parseInt($(this).parents("p").attr("data-page"));
	    var pageInfo = getPageInfo(page);
	    var l = parseFloat(bdr[0]) * pageInfo.width - 4; // 8.2677 inch * 100dpi
	    var t = parseFloat(bdr[1]) * pageInfo.height - 4;// 11.6929 inch * 100dpi
	    var w = parseFloat(bdr[2]) * pageInfo.width - l;
	    var h = parseFloat(bdr[3]) * pageInfo.height - t;
	    var wid = $(this).attr("id");
	    var box = '<div class="wordbox" style="left:' + l.toString() + 'px;top:' + t.toString() + 'px;width:' + w.toString() + 'px;height:' + h.toString() + 'px;" />';
	    $("#paper").remove("#div.wordbox");
	    $("#paper").append(box);
	    $("#paper div.wordbox").show();
	},
	function() {
	    // $(this).parents("div.section").css("border", "1px solid #FFFFFF");
	    $("#paper div.wordbox").remove();
	}
    );

    // マウスクリック時にそこまでスクロール
    $("#iframe_xhtml").contents().find("p").click(function() {
	// その位置までスクロール
	var paper = $("#paper");
	var paper_x = paper.offset().left;
	var paper_y = paper.offset().top;
	var box = $("#paper div.box");
	var box_x = box.offset().left - paper_x; // offset は絶対位置
	var box_y = box.offset().top - paper_y;
	/*
        var l = $("#paper").scrollLeft();
	var w = $("#paper").width();
	var t = $("#paper").scrollTop();
	var h = $("#paper").height();
	console.debug(box_x, box_y, l, t);
        */
	$("#paper").animate({
	    scrollLeft: box_x - 50,
	    scrollTop: box_y - 50
	}, 500, 'swing');
    });
    
}
