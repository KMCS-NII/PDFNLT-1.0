// 最初に表示するページ
// var default_paper = "C02-1045"; index.php で指定する

var keyprefix = "pdfanalyzer.line_checker.linedata";

// 表示中の論文とページ
var current_paper = null;
var current_page = 0; // 最初のページが 1 なので注意
var npages = 0; // 表示中の論文のページ数
var current_layout = 0; // レイアウト
var papers;

// 起動時の初期設定
$(document).ready(function() {

    function make_options(data) {
        return data.map(function make_option(datum) {
	    return '<option>' + datum + '</option>';
	}).join('');
    }
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
	$('#paper_list').html(make_options(papers));
    });

    $.get('train/labels.json').then(function(labels) {
	$('#label_list').html(make_options(labels));
    });

    assignActions();
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
    }).focus(function(e) {
	$(e.target).select();
    });

/*
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
*/

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

    // ウェブストレージ
    if (window.localStorage) {
	$("#line_save_button").show();
	$("#line_load_button").show();
    }

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

function scrollToLoc() {
    var [page, x, y] = loc;
    $('#table_line tr[data-page="' + page + '"]').each(function(i, tr) {
	var [_, x1, y1, x2, y2] = $(tr).data('bdr').split(' ').map(parseFloat);
	if (x1 < x && x < x2 && y1 < y && y < y2) {
	    selectLine($(tr).attr('data-line'))
	    showPaperImage(current_paper, page + 1);
	    return false;
	}
    });
}

// トレーニングデータを読み込む
var csv_data = [];
function showPaperLine(code, dir) {
    var url = "train/" + code + ".csv";
    $.ajax({
	url: url,
	dataType: "html",
	cache: false,
	success: function(data) {
	    updatePaperLineFromText(data, false);
	    if (loc) {
		scrollToLoc();
	    }
	}
    });
    return;
}

// テキストデータから Line データを更新する
// サーバから読み込んだ時
// ファイルドロップ時
function updatePaperLineFromText(text, docheck = false) {
    var lines = text.split("\n");
    new_data = [];
    for (i = 0; i < lines.length; i++) {
	var line = lines[i];
	if (line == "") {
	    break; // 最後の行は "\n" で終わっている
	}
	var args = line.split("\t");
	if (args.length == 4) {
	    args.push("0");
	}
	if (!docheck) {
	    args[4] = 0;
	} else {
	    if (args[1] != csv_data[i][1]) {
		alert("Line text does not match, line:" + (i + 1));
		return false;
	    }
	}
	new_data.push(args);
    }
    updatePaperLine(new_data);
}

function escapeAttribute(str) {
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}
function escapeText(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;');
}

// Line データを更新する
function updatePaperLine(data) {
    var reErr = /^#/;
    var html = "";
    for (var i = 0; i < data.length; i++) {
	var args = data[i];
	var params = args[3].split(" ");
	var tr_class = "line";
	var td_class = "";
	var val = args[0];
	if (reErr.test(val)) {
	    tr_class += " err";
	    val = val.substr(2);
	}
	if (args[4] == 1) {
	    td_class += ' class="edited"';
	    newEdited = true;
	}
	html += '<tr class="' + tr_class + '" data-page="' + params[0] + '" data-line="' + i + '" data-bdr="' + args[3] + '"><td>' + i.toString() + '</td><td' + td_class + '><input list="label_list" value="' + escapeAttribute(val) + '" data-orig="' + escapeAttribute(args[0]) + '"></td><td>' + escapeText(args[1]) + "</td></tr>\n";
	npages = parseInt(params[0], 10) + 1; // ページ数
    }
    setEdited(false);
    $("table#table_line tbody").html(html);
    
    csv_data = data;
    updateErrorCount();
}

// エラー行数を更新する
function updateErrorCount() {
    var n = $("tr.err:not(tr:has(td.edited))").size();
    $("span#disp_error").text("Error:" + n.toString());
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

var edited = false;
function setEdited(newEdited) {
    edited = newEdited;
    $("#line_save_button").prop('disabled', !edited);
    $("#line_update_button").prop('disabled', !edited);
}


// イベントアクションをセット
function assignActions() {
    // バインド済みの処理を多重定義しないように削除
    $(window).off('beforeunload');
    $("#table_line")
	.off('mouseenter', 'tr.line')
	.off('mouseleave', 'tr.line')
	.off('click', 'tr.line');
    $("tr.line").unbind('click');
    $("tr.line").unbind('dblclick');
    $("#paper_image").unbind('click');
    $("#line_correct_button").unbind('click');
    $("#line_download_button").unbind('click');
    $("#line_update_button").unbind('click');
    $("#line_save_button").unbind('click');
    $("#line_load_button").unbind('click');
    $("#line_save_button").attr('disabled', 'disabled');
    $("#line_load_button").attr('disabled', 'disabled');
    $("#paper_line").unbind('dragover').unbind('drop');
    

    $(window).on('beforeunload', function(e) {
	if (edited) {
	    var message = "The modified data will be lost. Is it OK?";
	    e.returnValue = message;
	    return message;
	}
    });

    // マウスオーバー時にボックスを表示
    $("#table_line").on('mouseenter', "tr.line", function() {
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
	$("#paper div.box").click(function() {
	    var line = $(this).attr("data-line");
	    selectLine(line);
	});
	$("#paper div.box").show();
    }).on('mouseleave', "tr.line", function() {
	$(this).removeClass('hovered');
	if (current_layout != 3) {
	    $("#paper div.box").remove();
	}
    })
    
    // マウスクリック時にクリックした位置までスクロール
    .on('click', 'tr.line', function() {
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
    })

    .on('keydown', 'input', function(e) {
	if (e.which == 13) { // enter
	    var $input = $(e.target);
	    var $tr = $input.closest('tr');
	    var $td = $input.closest('td');
	    var lineno = $tr.data("line");
	    var val;
	    if (e.shiftKey) {
		var $prevInput = $('#table_line [data-line="' + (lineno - 1) + '"] input');
		if ($prevInput) {
		    val = $prevInput.val();
		    $input.val(val);
		}
	    } else {
		val = $input.val();
	    }
	    if (csv_data[lineno][0] !== val) {
		csv_data[lineno][4] = 1;
		csv_data[lineno][0] = val;
		$td.addClass('edited');
		$tr.removeClass('err');
		setEdited(true);
		updateErrorCount();
	    }
	    var $nextInput = $('#table_line [data-line="' + (lineno + 1) + '"] input');
	    $nextInput.focus().select();
	    return false;
	} else if (e.which == 27) {
	    var $input = $(e.target);
	    var $tr = $input.closest('tr');
	    var $td = $input.closest('td');
	    var lineno = $tr.data("line");
	    var val = $input.val();
	    var orig = $input.data('orig');
	    if (orig.startsWith('# ')) {
		orig = orig.substr(2);
		$tr.addClass('err');
		updateErrorCount();
	    }
	    $input.val(orig);
	    $td.removeClass('edited');
	    var $prevInput = $('#table_line [data-line="' + (lineno - 1) + '"] input');
	    $prevInput.focus().select();
	}
    })

    .on('focus', 'input', function(e) {
	$(e.target).select();
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

/*
    // マウスダブルクリック時にクリックした位置までスクロール
    $("tr.line").dblclick(function() {
	var lineno = $(this).attr("data-line");
	var td = $(this).children("td").eq(1);
	var l = td.offset().left;
	var t = td.offset().top;
	var w = td.width() + 2; // padding の分
	var h = td.height() + 2;
	var val = csv_data[lineno][0];
	if (val.substr(0, 2) == '# ') {
	    val = val.substr(2);
	}
	var input = '<input class="labelinput" data-lineno="' + lineno + '" style="position:absolute;left:' + l.toString() + 'px;top:' + t.toString() + 'px;width:' + w.toString() + 'px;height:' + h.toString() + 'px;" value="' + val + '"/>';
	td.append(input);
	$("body").keypress(function(e) {
	    if (e.which == 13) { // enter
		$(".labelinput").each(function() {
		    var val = $(this).val();
		    var lineno = $(this).attr('data-lineno');
		    if (csv_data[lineno][0] != val) {
			var td = $(this).parent("td").eq(0);
			// ラベルが変更された
			csv_data[lineno][4] = 1;
			csv_data[lineno][0] = val;
			td.html(val);
			td.addClass("edited");
			$("#line_save_button").removeAttr("disabled");
			// 他の画面に移動する前に確認する
			$(window).on('beforeunload', function() {
			    return "The modified data will be lost. Is it OK?";
			});
			updateErrorCount();
		    }
		});
		$(".labelinput").remove();
		$("body").unbind('keypress');
		return false;
	    } else if (e.which == 0) { // escape
		$(".labelinput").remove();
		$("body").unbind('keypress');
		return false;
	    }
	});
	$(".labelinput").show();
    });
*/

    //  Download ボタン
    $("#line_download_button").click(function() {
	// タブ区切りテキストを用意
	var text = "";
	for (var i = 0; i < csv_data.length; i++) {
	    var line = csv_data[i];
	    for (var j = 0; j < line.length; j++) {
		if (j > 0) {
		   text += "\t";
		}
		text += line[j];
	    }
	    text += "\n";
	}
	/*
	// Unicode コード配列に変換
	var unicode_code_array = [];
	for (i = 0; i < csv.length; i++) {
	    unicode_code_array.push(csv.charCodeAt(i));
	}
	// ShiftJIS コード配列に変換 for MS-Excel
	var sjis_code_array = Encoding.convert(
	    unicode_code_array,
	    'SJIS',
	    'UNICODE'
	);
	// 文字コード配列をTypedArrayに変換する
	var uint8_array = new Uint8Array(sjis_code_array);
	 */

	// 指定されたデータを保持するBlobを作成する
	// var blob = new Blob([uint8_array], {type: 'text/csv'});
	var blob = new Blob([text], {type: 'application/octet-stream'});
	var filename = current_paper + ".csv";

	if (window.navigator.msSaveBlob) {
	    window.navigator.msSaveOrOpenBlob(blob, filename);
	} else {
	    var aobj = document.createElement("a");
	    if (aobj.download === undefined) {
		alert("Please use modern browsers ;(");
	    } else {
		aobj.href = window.URL.createObjectURL(blob);
		aobj.download = filename;
		aobj.style = "visibility:hidden";
		document.body.appendChild(aobj);
		aobj.click();
		document.body.removeChild(aobj);
	    }
	}
	return false;
    });

    $("#line_update_button").click(function() {
        var labels = [];
	for (var i = 0; i < csv_data.length; i++) {
	    labels.push(csv_data[i][0]);
	}
	$.post(location.href, {
	    paper: current_paper,
	    labels: JSON.stringify(labels),
	});
    });

    if (window.localStorage) {
	var itemkey = keyprefix + "." + current_paper;
	// Save ボタン -> Web local storage に保存
	$("#line_save_button").click(function() {
	    var json_text = JSON.stringify(csv_data);
	    window.localStorage.setItem(itemkey, json_text);
	    $("#line_load_button").removeAttr("disabled");
	    alert("Saved to the browser storage.");
	    $(window).off('beforeunload');
	});

	// Load ボタン
	if (window.localStorage.getItem(itemkey) != null) {
	    $("#line_load_button").removeAttr("disabled");
	}
	$("#line_load_button").click(function() {
	    var json_text = window.localStorage.getItem(itemkey);
	    var csv_data = JSON.parse(json_text);
	    updatePaperLine(csv_data);
	    $("#line_save_button").attr("disabled", "disabled");
	    alert("Loaded from browser storage.");
	});
    }

    // ファイルドロップ
    var dragto = $("#paper_line");
    dragto.bind("dragover", function(e) {
	e.preventDefault();
	dragto.css("border", "1px solid #FF8888");
    });
    dragto.bind("drop", function(e) {
	dragto.css("border", "1px solid black");
	e.preventDefault();
	var data_transfer = e.originalEvent.dataTransfer;
	var file_list = data_transfer.files;
	if (!file_list) return false;
	for (var i = 0; i < file_list.length; i++) {
	    var file = file_list[i];
	    if (file.name == current_paper + ".csv") {
		var reader = new FileReader();
		reader.onload = function(e2) {
		    var data = e2.target.result;
		    updatePaperLineFromText(data, true);
		    $("#line_save_button").removeAttr("disabled");
		    alert("Loaded from the dropped file.");
		};
		reader.readAsText(file);
		return false;
	    }
	}
	alert("The file is not for the current paper.");
	return false;
    });

}

// 指定したlineを選択する
var selectedLine = null;
function selectLine(line) {
    if (selectedLine) {
	selectedLine.removeClass('selected');
    }
    if (line == null) {
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
