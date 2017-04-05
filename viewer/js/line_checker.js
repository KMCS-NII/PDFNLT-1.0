// 最初に表示するページ
// var default_paper = "C02-1045"; index.php で指定する

// 表示中の論文とページ
var current_paper = null;
var current_page = 0; // 最初のページが 1 なので注意
var npages = 0; // 表示中の論文のページ数
var current_layout = 0; // レイアウト

// 起動時の初期設定
$(document).ready(function() {

    readNewPaper(default_paper);
    /*
    // 画面レイアウトをブラウザの大きさに合わせて変更
    resetLayout();

    // 論文 トレーニングデータ を表示
    showPaperLine(default_paper);

    // 論文 PDF 画像 を表示
    showPaperImage(default_paper, 1);
    
    current_paper = default_paper;
    current_page = 1;
     */

    // 論文セレクタの操作
    $("#paper_select").change(function() {
	var new_paper = $(this).val();
	readNewPaper(new_paper);
    });

    $("#paper_select_input").change(function() {
	var input = $(this).val();
	$("#paper_select option").each(function() {
	    if ($(this).value == input
		|| $(this).html() == input) {
		// console.debug("match:" + $(this).value);
		readNewPaper(input);
	    }
	});
    });

    $("#paper_select_input_button").click(function() {
	var new_paper = $("#paper_select_input").val();
	readNewPaper(new_paper);
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

// 新しいトレーニングファイルを開く
function readNewPaper(new_paper) {
    if (new_paper != current_paper) {
	showPaperLine(new_paper);
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
    $("#paper_line").find("tr").removeClass('hovered');

    switch (current_layout) {
    case 0: // 横２分割
	var content_width = (w - 4) / 2;
	var content_height = h - 5;
	// $("#container").css("display", "block");
	$("#container").css("flex-direction", "row");
	$("div.line").css("display", "inline-block");
	$("div.pdf").css("display", "inline-block");
	$("#paper_line").show();
	$("#paper").show();
	$("#page_number").show();
	$("#paper_line").width(content_width);
	$("#paper_line").height(content_height);
	$("#paper").width(content_width);
	$("#paper").height(content_height);
	//$(".line").width(content_width);
	//$(".line").height(content_height);
	//$(".pdf").width(content_width);
	//$(".pdf").height(content_height);
	break;
    case 1: // 縦２分割
	var content_width = w - 4;
	var content_height = (h - 5) / 2;
	//$("#container").css("display", "block");
	$("#container").css("flex-direction", "column");
	$("div.line").css("display", "block");
	$("div.pdf").css("display", "block");
	$("#paper_line").show();
	$("#paper").show();
	$("#page_number").show();
	$("#paper_line").width(content_width);
	$("#paper_line").height(content_height);
	$("#paper").width(content_width);
	$("#paper").height(content_height);
	//$(".line").width(content_width);
	//$(".line").height(content_height);
	//$(".pdf").width(content_width);
	//$(".pdf").height(content_height);
	break;
    case 2: // XHTML のみ
	var content_width = w - 4;
	var content_height = h - 5;
	// $("#container").css("display", "block");
	$("div.line").css("display", "block");
	$("div.pdf").css("display", "none");
	$("#paper_line").show();
	$("#paper").hide();
	$("#page_number").hide();
	$("#paper_line").width(content_width);
	$("#paper_line").height(content_height);
	//$("#paper").width(0);
	//$("#paper").height(0);
	//$(".line").width(content_width);
	//$(".line").height(content_height);
	//$(".pdf").width(0);
	//$(".pdf").height(0);
	break;
    case 3: // PDF のみ
	var content_width = w - 4;
	var content_height = h - 5;
	// $("#container").css("display", "block");
	$("div.line").css("display", "none");
	$("div.pdf").css("display", "block");
	$("#paper_line").hide();
	$("#paper").show();
	$("#page_number").show();
	//$("#paper_line").width(0);
	//$("#paper_line").height(0);
	$("#paper").width(content_width);
	$("#paper").height(content_height);
	//$(".line").width(0);
	//$(".line").height(0);
	//$(".pdf").width(content_width);
	//$(".pdf").height(content_height);
	break;
    }
    var paper_offset = $("#paper").offset();
    $("div#page_number").css("top", paper_offset.top + 3);
    $("div#page_number").css("left", paper_offset.left + 3);
}

// トレーニングデータを読み込む
function showPaperLine(code) {
    var url = "train/" + code + ".csv";
    var reErr = /^#/;
    $.ajax({
	url: url,
	dataType: "html",
	cache: false,
	success: function(data) {
	    var lines = data.split("\n");
	    var html = "";
	    for (i = 0; i < lines.length; i++) {
		var line = lines[i];
		if (line == "") {
		    break; // 最後の行は "\n" で終わっている
		}
		var args = line.split("\t");
		var params = args[3].split(" ");
		var tr_class = "line";
		if (reErr.test(args[0])) {
		    tr_class += " err";
		}
		html += '<tr class="' + tr_class + '" data-page="' + params[0] + '" data-line="' + i + '" data-bdr="' + args[3] + '"><td>' + i.toString() + "</td><td>" + args[0] + "</td><td>" + args[1] + "</td></tr>\n";
		npages = parseInt(params[0], 10) + 1; // ページ数
	    }
	    $("table#table_line tbody").html(html);
	    assignActions();
	}
    });
    return;
}

// ページ画像を表示する
// 先頭ページは 1
function showPaperImage(code, page) {
    if (code != current_paper || page != current_page) {
	var image_url = "xhtml/images/" + code + "/" + code + "-" + ('0' + page).slice(-2) + '.png';
	$("#paper_image").attr("src", image_url);
	current_page = page;
	$("div#page_number").html("p." + current_page.toString());
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
    $("tr.line").hover(
	function() {
	    $(this).addClass('hovered');
	    var str_bdr = $(this).attr("data-bdr");
	    var bdr = str_bdr.split(' ');
	    if (bdr.length != 5) return false;
	    var page = parseInt(bdr[0]);
	    showPaperImage(current_paper, page + 1);

	    var l = parseFloat(bdr[1]);
	    var t = parseFloat(bdr[2]);
	    var w = parseFloat(bdr[3]) - l;
	    var h = parseFloat(bdr[4]) - t;
	    var box = '<div class="box" data-page="' + (page + 1).toString() + '" style="left:' + l.toString() + 'px;top:' + t.toString() + 'px;width:' + w.toString() + 'px;height:' + h.toString() + 'px;"/>';
	    $("#paper").remove("#div.box");
	    $("#paper").append(box);
	    $("#paper div.box").show();
	    $("#paper div.box").click(function() {
		var line = $(this).attr("data-line");
		selectLine(line);
	    });
	},
	function() {
	    $(this).removeClass('hovered');
	    if (current_layout != 3) {
		$("#paper div.box").remove();
	    }
	}
    );

    // マウスクリック時にクリックした位置までスクロール
    $("tr.line").click(function() {
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
	var x = e.offsetX;
	var y = e.offsetY;

	// 対応する tr 要素を取得する
	var tr_list = $("tr.line[data-page=" + (current_page - 1).toString() + "]");
	var line = null;
	tr_list.each(function() {
	    // 各要素のうち、クリックした座標を含むものを検索
	    var l = $(this);
	    var coords = l.attr("data-bdr").split(" ");
	    if (coords[1] <= x
		&& coords[2] <= y
		&& coords[3] >= x
		&& coords[4] >= y) {
		line = l.attr("data-line");
		return;
	    }
	});

	selectLine(line);
    });
}

// 指定したlineを選択する
var selectedLine = null;
function selectLine(line) {
    if (selectedLine) {
	selectedLine.removeClass('selected');
    }
    if (!line) {
	return false;
    }

    if (current_layout == 3) {
	// PDF のみの場合にクリックすると
	// XHTML に切り替えて対応部分に移動する
	current_layout = 2;
	resetLayout();
    }

    var l = $("tr.line[data-line=" + line + "]");
    selectedLine = l;
    l.addClass('selected');

    var paper_y = $("div#paper_line").offset().top;
    var t = $("div#paper_line").scrollTop();
    var h = $("div.line").height();
    var target_y = l.eq(0).offset().top - paper_y + t;
    if (target_y < t + 10 || target_y > t + h - 10) {
	$("div#paper_line").scrollTop(target_y - 50);
    }
}
