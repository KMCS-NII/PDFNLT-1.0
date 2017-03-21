var current_page = 0;
// var default_paper = "95760_1_1";
var current_paper = null;
$(document).ready(function() {
    readPaper(default_paper);
    showPage(default_paper, 1);
    current_paper = default_paper;
    current_page = 1;
    $("#paper_select").change(function() {
	var new_paper = $(this).val();
	if (new_paper != current_paper) {
	    readPaper(new_paper);
	    showPage(new_paper, 1);
	    current_paper = new_paper;
	    current_page = 1;
	}
    });
    $("#paper_select_input_button").click(function() {
	var new_paper = $("#paper_select_input").val();
	if (new_paper != current_paper) {
	    readPaper(new_paper);
	    showPage(new_paper, 1);
	    current_paper = new_paper;
	    current_page = 1;
	}
    });
});

// Read XHTML content into the iframe
function readPaper(code) {
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
function showPage(code, page) {
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
	    showPage(current_paper, page + 1);

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
}

function renderParagraph(json) {
    var html = '';
    var paragraphs = [];
    $("div.box").remove();
    for (box of json) {
	html += '<div class="boxtext"><div class="boxtype">' + box['boxType'] + '</div>';
	for (paragraph of box['paragraphs']) {
	    var n = paragraph['n'];
	    var idx = n;
	    if ('continued_from' in paragraph) {
		idx = paragraph['continued_from'];
		while (true) {
		    idx = paragraphs[idx];
		    if (paragraphs[idx] == idx) break;
		}
	    }
	    paragraphs[n] = idx;
	    var page = paragraph['page'];
	    if (paragraph['bdr'] != null) {
		var l = parseInt(paragraph['bdr'][0]);
		var t = parseInt(paragraph['bdr'][1]);
		var w = parseInt(paragraph['bdr'][2]) - l;
		var h = parseInt(paragraph['bdr'][3]) - t;
		var box = '<div class="box" data-n="' + n + '" data-index="' + idx + '" data-page="' + (page + 1).toString() + '" style="left:' + l.toString() + 'px;top:' + t.toString() + 'px;width:' + w.toString() + 'px;height:' + h.toString() + 'px;" />';
		$("#paper").append(box);
	    }
	    if (paragraph['bdr'] == null || page == null) {
		html += '<p class="paragraph nobox" data-index="' + idx + '">' + paragraph['text'] + '</p>';
	    } else {
		html += '<p class="paragraph" data-index="' + idx + '" data-page="' + (page + 1).toString() + '">' + paragraph['text'] + '</p>';
	    }
	}
	html += '</div>';
    }
    $("#note").html(html);

    $("p.paragraph").hover(
	function () {
	    var index = $(this).attr('data-index');
	    var page = $(this).attr('data-page');
	    if (page != null) {
		showPage(current_paper, page);
		current_page = page;
	    }
	    var box = $("div.box[data-index=" + index + "][data-page=" + page + "]");
	    box.removeClass("box_on_other_page");
	    box.show();
	    var box = $("div.box[data-index=" + index + "][data-page!=" + page + "]");
	    box.addClass("box_on_other_page");
	    box.show();
	},
	function () {
	    var index = $(this).attr('data-index');
	    $("div.box[data-index=" + index + "]").hide();
	}
    );
}
