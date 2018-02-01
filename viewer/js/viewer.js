// 最初に表示するページ
// var default_paper = "C02-1045"; index.php で指定する

// 表示中の論文とページ
var current_paper = null;
var current_page = 0; // 最初のページが 1 なので注意
var npages = 0;     // 表示中の論文のページ数
var current_layout = 0; // レイアウト
var papers;


// 起動時の初期設定
$(document).ready(function() {
    var papersPromise = (function getPapers() {
	var papersJSON = sessionStorage.getItem('papers');
	if (papersJSON) {
	    return Promise.resolve(JSON.parse(papersJSON))
	} else {
	    return $.get('ajax.php?directory');
	}
    })();
    papersPromise.then(function(data) {
	papers = data;
	sessionStorage.setItem('papers', JSON.stringify(papers));
	$('#paper_list').html(papers.map(function(paper) {
	    return '<option>' + paper + '</option>';
	}).join(''));
    });

    readNewPaper(default_paper);
    /*
    // 画面レイアウトをブラウザの大きさに合わせて変更
    resetLayout();

    // 論文 XHTML を表示
    showPaperXhtml(default_paper);

    // 論文 PDF 画像 を表示
    showPaperImage(default_paper, 1);
    
    current_paper = default_paper;
    current_page = 1;
     */

    // 論文セレクタの操作
    $("#paper_select").change(function() {
	var new_paper = $(this).val();
	readNewPaper(new_paper);
    }).focus(function(e) {
	$(e.target).select();
    });

    $("#layout_select_button").click(function() {
	current_layout = (current_layout + 1) % 4;
	resetLayout();
    });

    $("#prev_page_button").click(function() {
	if (current_page > 1) {
	    showPaperImage(current_paper, current_page - 1);
	    resetLayout();
	}
    });

    $("#next_page_button").click(function() {
	console.debug(current_page, npages);
	if (current_page < npages) {
	    showPaperImage(current_paper, current_page + 1);
	    resetLayout();
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

// 新しい XHTML ファイルを開く
function readNewPaper(new_paper) {
    if (new_paper != current_paper) {
	showPaperXhtml(new_paper);
	showPaperImage(new_paper, 1);
	current_paper = new_paper;
	current_page = 1;
	resetLayout();
	$("#paper_select").val(new_paper);
    }
}

// 画面レイアウトを画面サイズに合わせて変更
function resetLayout() {
    var w = $(window).width();
    var h = $(window).height();
    h -= $("div#menu").height();
    $("#iframe_xhtml").contents().find("p").removeClass('hovered');

    switch (current_layout) {
    case 0: // 横２分割
	var content_width = (w - 4) / 2;
	var content_height = h - 5;
	// $("#container").css("display", "block");
	$("#container").css("flex-direction", "row");
	$("div.xhtml").css("display", "inline-block");
	$("div.pdf").css("display", "inline-block");
	$("#iframe_xhtml").show();
	$("#paper").show();
	$("#page_number").show();
	$("#iframe_xhtml").width(content_width);
	$("#iframe_xhtml").height(content_height);
	$("#paper").width(content_width);
	$("#paper").height(content_height);
	//$(".xhtml").width(content_width);
	//$(".xhtml").height(content_height);
	//$(".pdf").width(content_width);
	//$(".pdf").height(content_height);
	break;
    case 1: // 縦２分割
	var content_width = w - 4;
	var content_height = (h - 5) / 2;
	//$("#container").css("display", "block");
	$("#container").css("flex-direction", "column");
	$("div.xhtml").css("display", "block");
	$("div.pdf").css("display", "block");
	$("#iframe_xhtml").show();
	$("#paper").show();
	$("#page_number").show();
	$("#iframe_xhtml").width(content_width);
	$("#iframe_xhtml").height(content_height);
	$("#paper").width(content_width);
	$("#paper").height(content_height);
	//$(".xhtml").width(content_width);
	//$(".xhtml").height(content_height);
	//$(".pdf").width(content_width);
	//$(".pdf").height(content_height);
	break;
    case 2: // XHTML のみ
	var content_width = w - 4;
	var content_height = h - 5;
	// $("#container").css("display", "block");
	$("div.xhtml").css("display", "block");
	$("div.pdf").css("display", "none");
	$("#iframe_xhtml").show();
	$("#paper").hide();
	$("#page_number").hide();
	$("#iframe_xhtml").width(content_width);
	$("#iframe_xhtml").height(content_height);
	//$("#paper").width(0);
	//$("#paper").height(0);
	//$(".xhtml").width(content_width);
	//$(".xhtml").height(content_height);
	//$(".pdf").width(0);
	//$(".pdf").height(0);
	break;
    case 3: // PDF のみ
	var content_width = w - 4;
	var content_height = h - 5;
	// $("#container").css("display", "block");
	$("div.xhtml").css("display", "none");
	$("div.pdf").css("display", "block");
	$("#iframe_xhtml").hide();
	$("#paper").show();
	$("#page_number").show();
	//$("#iframe_xhtml").width(0);
	//$("#iframe_xhtml").height(0);
	$("#paper").width(content_width);
	$("#paper").height(content_height);
	//$(".xhtml").width(0);
	//$(".xhtml").height(0);
	//$(".pdf").width(content_width);
	//$(".pdf").height(content_height);
	break;
    }
    var paper_offset = $("#paper").offset();
    $("div#page_number").css("top", paper_offset.top + 3);
    $("div#page_number").css("left", paper_offset.left + 3);
}

// XHTML を iframe に読み込む
function showPaperXhtml(code) {
    // XHTML を表示
    var url = "xhtml/" + code + ".xhtml";
    var jo = $("#iframe_xhtml")[0];
    if (jo) {
	$("#iframe_xhtml").on('load', function() {
	    $('#iframe_xhtml').contents().find('head').append('<style>p.selected { background: #FFC; }</style>');
	    $('#iframe_xhtml').contents().find('head').append('<style>p.hovered { background: #FCC; }</style>');
	    // var o = $("#iframe_xhtml").contents().find("div.box[data-name='Title']>p");
	    assignActions();
	    // ページ数を取得
	    npages = $("#iframe_xhtml").contents().find("pages>page").length;
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
	$("div#page_number span#page").html("p." + current_page.toString());
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

    // XHTML 表示エリアのイベント

    $("#iframe_xhtml").contents().on('dblclick', 'span.word', function(evt) {
	var $word = $(evt.target);
	var page = parseInt($word.closest('p').data('page'));
	var [x1, y1, x2, y2] = $word.data('bdr').split(',').map(parseFloat);
	var base = location.href.replace(/\/[^/]*$/, '/');
	var $page = $("#iframe_xhtml").contents().find('pages page:nth-child(' + (page + 1) + ')');
	// assumption: inches ("##.## in")
	var height = parseFloat($page.attr('height'));
	var width = parseFloat($page.attr('width'));
	var url = base + "line_checker.php?code=" + current_paper + "&loc=" + page + "," +
	  ((x1 + x2) * width * 100 / 2).toFixed(2) + "," + ((y1 + y2) * height * 100 / 2).toFixed(2);
	window.open(url, '_self');
    });

    // マウスオーバー時にボックスを表示
    $("#iframe_xhtml").contents().find("p").hover(
	function() {
	    
	    $(this).addClass('hovered');
	    var pid = $(this).attr('id');
	    // selectParagraphInXhtml(pid);
	    
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
	    var box = '<div class="boxlabel" style="left:' + l.toString() + 'px;top:' + (t - 18).toString() + 'px;">' + section_label + ':' + box_label + ' [' + pid + ']</div>';
	    box = box + '<div class="box" data-page="' + (page + 1).toString() + '" style="left:' + l.toString() + 'px;top:' + t.toString() + 'px;width:' + w.toString() + 'px;height:' + h.toString() + 'px;" data-pid="' + pid + '"/>';
	    $("#paper").remove("div.box, div.boxlabel");
	    $("#paper").append(box);
	    $("#paper div.box").show();
	    $("#paper div.box").click(function() {
		var id = $(this).attr("data-pid");
		selectParagraphInXhtml(id);
	    });
	},
	function() {
	    $(this).removeClass('hovered');
	    if (current_layout != 3) {
		$("#paper div.box").remove();
		$("#paper div.boxlabel").remove();
	    }
	}
    );
    $("#iframe_xhtml").contents().find("span.word").hover(
	function() {
	    var word_id = $(this).attr("id");
	    $("#paper").remove("#div.wordbox");
	    $("#iframe_xhtml").contents().find("span.word[id=" + word_id + "],span.word[data-refid=" + word_id + "]")
		.each(function() {
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
		    $("#paper").append(box);
		    $("#paper div.wordbox").show();
		});
	},
	function() {
	    // $(this).parents("div.section").css("border", "1px solid #FFFFFF");
	    $("#paper div.wordbox").remove();
	}
    );

    // マウスクリック時にクリックした位置までスクロール
    $("#iframe_xhtml").contents().find("p").click(function() {
	if (current_layout == 2) {
	    // XHTML のみの場合にクリックすると
	    // PDF に切り替えて対応部分に移動する
	    current_layout = 3;
	    resetLayout();
	}

	// その位置までスクロール
	var paper = $("#paper");
	var paper_x = paper.offset().left;
	var paper_y = paper.offset().top;
	var box = $("#paper div.box");
	var box_x = box.offset().left - paper_x; // offset は絶対位置
	var box_y = box.offset().top - paper_y;
        var l = $("#paper").scrollLeft();
	var w = $("#paper").width();
	var t = $("#paper").scrollTop();
	var h = $("#paper").height();
	// console.debug("box_x:" + box_x + ", box_y:" + box_y);
	// console.debug("l:" + l + ", w:" + w + ", t:" + t + ", h:" + h);
	if (box_x < 10 || box_y < 10
	    || box_x > w - 10 || box_y > h - 10) {
	    $("#paper").animate({
		scrollLeft: box_x - 10,
		scrollTop: box_y - 10
	    }, 500);
	}
    });

    // PDF 表示エリアのイベント
    $("#paper_image").click(function(e) {
	// $("#iframe_xhtml").contents().find("p").removeClass('selected');
	var paper_x = e.offsetX / $("#paper_image").width();
	var paper_y = e.offsetY / $("#paper_image").height();;

	// XHTML 内で対応する p 要素を取得する
	var p_list = $("#iframe_xhtml").contents().find("p[data-page='" + (current_page - 1).toString() + "']");
	var id = null;
	p_list.each(function() {
	    // 各要素のうち、クリックした座標を含むものを検索
	    var p = $(this);
	    var coords = p.attr("data-bdr").split(",");
	    if (coords[0] <= paper_x
		&& coords[1] <= paper_y
		&& coords[2] >= paper_x
		&& coords[3] >= paper_y) {
		id = p.attr("id");
		return;
	    }
	});

	selectParagraphInXhtml(id);
    });
}

// XHTML 内の id で指定したパラグラフを選択する
var selectedParagraph = null;
function selectParagraphInXhtml(id) {
    $("#iframe_xhtml").contents().find("p").removeClass('selected');
    if (!id) {
	return false;
    }

    if (current_layout == 3) {
	// PDF のみの場合にクリックすると
	// XHTML に切り替えて対応部分に移動する
	current_layout = 2;
	resetLayout();
    }

    var c = $("#iframe_xhtml").contents();
    var p = c.find("p#" + id);
    if (selectedParagraph) {
	selectedParagraph.removeClass('selected');
	selectedParagraph = p;
    }
    p.addClass('selected');

    var paper_y = $("#iframe_xhtml").offset().top;
    var t = c.scrollTop();
    var target_y = p.eq(0).offset().top - paper_y;
    var h = $("div.xhtml").height();
    if (target_y < t + 10 || target_y > t + h - 10) {
	$("#iframe_xhtml").contents().scrollTop(target_y - 50);
    }
}
