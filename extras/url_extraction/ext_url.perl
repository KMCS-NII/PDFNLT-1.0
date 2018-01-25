#!/usr/bin/perl

open(IN,$ARGV[0]);
$file=$ARGV[0];
$file=~s/.xhtml//;

while($sent=<IN>){
    chomp $sent;
    if($sent=~/<p id=\"(.*?)\" data-text=\"(.*?)\">/){
	$p_id=$1;
	$text=$2;
	$text=~s/\[//g;
	$text=~s/\]//g;
	$text_org=$text;
	$count=0;
	while($text=~/(http|htm)/ && $pre_text ne $text && $count<10){
	    $count++;
	    $pre_text=$text;
	    $url='';
	    if($text=~/(http[^<]+?html?)/){
		$url=$1;
	    }
	    elsif($text=~/(http[^<]+?)(\"| |\)|\')/){
		$url=$1;
	    }
	    elsif($text=~/\(([^\)]+?(htm|asp|pdf|html))\)/){
		$url=$1;
	    }
	    $url=~s/(\.|\,|\'|\;|\"|\”)$//;
	    if(length($url)<12){$url=''}
	    elsif($url=~/ /){$url=''}
	    if(length($url)>0){
#		    print "<C>$url_correct_all</C>\t<E>$url</E>\t$text_org\n";
		if($check{$url}!=1){
#		    print $url."\n";
		    $web_title='';
		    $web_title=&get_title($url);
		    $text_tmp=$text;
		    $text_tmp=~s/\Q$url\E/<webcitation>/;
		    $sentences=&split_sent($text_tmp);
#		    print $sentences."\n";
#		    $type=&citation_type_identification($sentences);
#		    print "$file\t$url\t$type\t$web_title\n";
		    print "$file\t$web_title\t$url\n";
		    $web_title='';
		    $text=~s/\Q$url\E//;
		    $check{$url}=1;
		}
	    }
	}
    }
}

sub get_title{
    my $url=shift(@_);
    my $found302=0;
    my $title='';
    open(CURL,"curl --connect-timeout 5 \'$url\' |nkf -w|");
    while($s=<CURL>){
	chomp $s;
	if($s=~/<title>(.*?)<\/title>/i){
	    $title=$1;
#	    print "TITLE:".$title."\n";
#	    if($title=~/(302|redirect|document (has )?moved|sorry|303)/i){
#		print 'TITLE '.$title."\n";
#		while($s=<CURL>){
#		    print $s;
#		    if($s=~/<a href=\"(.*?)\">/){
#			$title=&get_title($1);
#		    }
#		}		
#	    }
	    if($title=~/(400|403|404|521|503|301|302|303|not found|not be found|moved|available|redirect|sorry)/i){$title='';return()}
	    close(CURL);
	    $title=~s/\t/ /g;
	}
    }
    return($title);
}

sub split_sent{ # <p>タグ内の文書を文に分割
    my $sentences=shift(@_);
    while($sentences=~/\. [A-Z]/){
	$sentences=~s/(\.) ([A-Z])/$1 <linebreak>$2/;
    }
    while($sentences=~/\.[A-Z]/){
	$sentences=~s/(\.)([A-Z])/$1<linebreak>$2/;
    }
    return($sentences);
}

sub citation_type_identification{ # 引用タイプの判定
    my $sentences=shift(@_);

    if($sentences=~/<webcitation>/){

	# 段落の先頭から見て、最初のreferタグの個所の引用タイプを決定する。
#	$sentences=~s/<refer /<refer-target /;
#	$sentences=~s/<\/refer>/<\/refer-target>/;

	$sent24='';
#	my @sents=split(/<linebreak>/,$sentences);
	$sent24=$sentences;
	$sent24=~s/<linebreak>/\n/g;
	$tp=&rule_type_new_cue($sent24,'webcitation',$year);
#	$sentences=~s/<refer-target /<refer-target type=\"$tp\" /;
#	$sentences=~s/<refer-target/<refer-finish/;
#	$sentences=~s/<\/refer-target/<\/refer-finish/;
    }

#    $sentences=~s/<refer-finish/<refer/g;
#    $sentences=~s/<\/refer-finish/<\/refer/g;
#    $sentences=~s/<linebreak>//g;

#    return($sentences);
    return($tp);
}

sub rule_type_new_cue($$$){
    my $sent3,$sent4,$sent5,$citename,@next;
    my @next=();
    $sent3='';$sent4='';$sent5='';$citename='';$citeline=0;$endline=0;
    $sent3=$_[0];
    $citename=$_[1];
    $se=$_[2];
    @next=split(/\n/,$sent3);
    foreach $hoge (@next){
	if($hoge=~/$citename/){	
	    last;
	}
	$citeline++;
    }
    $endline=@next;
    if($next[$citeline+0]=~/[Aa]lthough the /){return('C');} # 1勝
    if($next[$citeline+1]=~/[Aa]lthough the /){return('C');} # なし
    if($next[$citeline+2]=~/[Aa]lthough the /){return('C');} # なし

    if($next[$citeline+0]=~/\, ?although /){return('C');} # なし
    if($next[$citeline+1]=~/\, ?although /){return('C');} # 1敗
    if($next[$citeline+2]=~/\, ?although /){return('C');} # 1勝

    if($next[$citeline+0]=~/Though[ ,]/){return('C');} # なし
    if($next[$citeline+1]=~/Though[ ,]/){return('C');} # なし
    if($next[$citeline+2]=~/Though[ ,]/){return('C');} # なし

    if($next[$citeline+0]=~/however\,.*?our /){return('C');} # 1勝
    if($next[$citeline+1]=~/however\,.*?our /){return('C');} # なし
    if($next[$citeline+2]=~/however\,.*?our /){return('C');} # 1勝
    if($next[$citeline+3]=~/however\,.*?our /){return('C');} # なし
    if($next[$citeline+4]=~/however\,.*?our /){return('C');} # なし

    if($next[$citeline+0]=~/however\,.*? they /){return('C');} # なし
    if($next[$citeline+1]=~/however\,.*? they /){return('C');} # 1勝
    if($next[$citeline+2]=~/however\,.*? they /){return('C');} # なし
    if($next[$citeline+3]=~/however\,.*? they /){return('C');} # なし

    if($next[$citeline+0]=~/however\,.*? not /){return('C');} # 1勝
    if($next[$citeline+1]=~/however\,.*? not /){return('C');} # 1勝
    if($next[$citeline+2]=~/however\,.*? not /){return('C');} # なし
    if($next[$citeline+3]=~/however\,.*? not /){return('C');} # なし
    if($next[$citeline+4]=~/however\,.*? not /){return('C');} # なし
    if($next[$citeline+5]=~/however\,.*? not /){return('C');} # なし

    if($next[$citeline+1]=~/However\,?/){return('C');} # 6勝
    if($next[$citeline+2]=~/However\,?/){return('C');} # 4勝
    if($next[$citeline+3]=~/However\,?/){return('C');} # なし
    if($next[$citeline+4]=~/However\,?/){return('C');} # なし
    if($next[$citeline+5]=~/However\,?/){return('C');} # 1勝

    if($next[$citeline+1]=~/however\, *the/){return('C');} # 1勝
    if($next[$citeline+2]=~/however\, *the/){return('C');} # 1勝
    if($next[$citeline+3]=~/however\, *the/){return('C');} # なし
    if($next[$citeline+4]=~/however\, *the/){return('C');} # なし
    if($next[$citeline+5]=~/however\, *the/){return('C');} # なし

    if($next[$citeline+1]=~/But /){return('C');} # なし
    if($next[$citeline+2]=~/But /){return('C');} # 1勝
    if($next[$citeline+3]=~/But /){return('C');} # なし
    if($next[$citeline+4]=~/But /){return('C');} # なし
    if($next[$citeline+5]=~/But /){return('C');} # 1勝

    if($next[$citeline+0]=~/but a /){return('C');} # なし
    if($next[$citeline+1]=~/but a /){return('C');} # なし
    if($next[$citeline+2]=~/but a /){return('C');} # なし


    if($next[$citeline+0]=~/but the /){return('C');} # 1勝1敗
    if($next[$citeline+1]=~/but the /){return('C');} # なし
    if($next[$citeline+2]=~/but the /){return('C');} # なし


    if($next[$citeline+1]=~/but it /){return('C');} # なし
    if($next[$citeline+2]=~/but it /){return('C');} # なし

    if($next[$citeline+0]=~/but is /){return('C');} # なし
    if($next[$citeline+1]=~/but is /){return('C');} # なし
    if($next[$citeline+2]=~/but is /){return('C');} # なし

    if($next[$citeline+0]=~/but are /){return('C');} # なし
    if($next[$citeline+1]=~/but are /){return('C');} # なし
    if($next[$citeline+2]=~/but are /){return('C');} # なし

    if($next[$citeline+0]=~/but rather /){return('C');} # なし
    if($next[$citeline+1]=~/but rather /){return('C');} # なし
    if($next[$citeline+2]=~/but rather /){return('C');} # なし

    if($next[$citeline+0]=~/but no /){return('C');} # 1勝
    if($next[$citeline+1]=~/but no /){return('C');} # なし
    if($next[$citeline+2]=~/but no /){return('C');} # なし

    if($next[$citeline+0]=~/[Bb]ut they/){return('C');} # 1勝
    if($next[$citeline+1]=~/[Bb]ut they/){return('C');} # なし
    if($next[$citeline+2]=~/[Bb]ut they/){return('C');} # なし

    if($next[$citeline+0]=~/[Bb]ut their/){return('C');} # なし
    if($next[$citeline+1]=~/[Bb]ut their/){return('C');} # なし
    if($next[$citeline+2]=~/[Bb]ut their/){return('C');} # なし

    if($next[$citeline+0]=~/[Bb]ut he /){return('C');} # なし
    if($next[$citeline+1]=~/[Bb]ut he /){return('C');} # なし
    if($next[$citeline+2]=~/[Bb]ut he /){return('C');} # なし

    if($next[$citeline+0]=~/[Bb]ut his /){return('C');} # なし
    if($next[$citeline+1]=~/[Bb]ut his /){return('C');} # なし
    if($next[$citeline+2]=~/[Bb]ut his /){return('C');} # なし

    if($next[$citeline+0]=~/[Bb]ut she /){return('C');} # 1勝
    if($next[$citeline+1]=~/[Bb]ut she /){return('C');} # なし
    if($next[$citeline+2]=~/[Bb]ut she /){return('C');} # なし

    if($next[$citeline+0]=~/[Bb]ut her /){return('C');} # なし
    if($next[$citeline+1]=~/[Bb]ut her /){return('C');} # なし
    if($next[$citeline+2]=~/[Bb]ut her /){return('C');} # なし

    if($next[$citeline+0]=~/[Bb]ut it/){return('C');} # 1勝
    if($next[$citeline+1]=~/[Bb]ut it/){return('C');} # なし
    if($next[$citeline+2]=~/[Bb]ut it/){return('C');} # なし

    if($next[$citeline+0]=~/[Bb]ut instead/){return('C');} # なし
    if($next[$citeline+1]=~/[Bb]ut instead/){return('C');} # なし
    if($next[$citeline+2]=~/[Bb]ut instead/){return('C');} # なし

    if($next[$citeline+0]=~/Instead ?\,/){return('C');}
    if($next[$citeline+1]=~/Instead ?\,/){return('C');}
    if($next[$citeline+2]=~/Instead ?\,/){return('C');}

    if($next[$citeline-1]=~/In spite of /){return('C');} # 1勝
    if($next[$citeline+0]=~/In spite of /){return('C');} # なし
    if($next[$citeline+1]=~/In spite of /){return('C');} # 1勝
    if($next[$citeline+2]=~/In spite of /){return('C');} # なし

    if($next[$citeline+1]=~/ does not /){return('C');}
    if($next[$citeline+2]=~/ does not /){return('C');}

    if($next[$citeline+0]=~/ did not /){return('C');}
    if($next[$citeline+1]=~/ did not /){return('C');}
    if($next[$citeline+2]=~/ did not /){return('C');}

    if($next[$citeline+0]=~/ that is not /){return('C');}
    if($next[$citeline+1]=~/ that is not /){return('C');}
    if($next[$citeline+2]=~/ that is not /){return('C');}

    if($next[$citeline+0]=~/ not be /){return('C');} # 1勝1負
    if($next[$citeline+1]=~/ not be /){return('C');} # なし
    if($next[$citeline+2]=~/ not be /){return('C');} # なし

    if($next[$citeline+0]=~/ it is not /){return('C');} # なし
    if($next[$citeline+1]=~/ it is not /){return('C');} # なし
    if($next[$citeline+2]=~/ it is not /){return('C');} # なし

    if($next[$citeline+0]=~/ this is not /){return('C');} # なし
    if($next[$citeline+1]=~/ this is not /){return('C');} # なし
    if($next[$citeline+2]=~/ this is not /){return('C');} # なし

    if($next[$citeline+0]=~/ was not /){return('C');} # なし
    if($next[$citeline+1]=~/ was not /){return('C');} # なし
    if($next[$citeline+2]=~/ was not /){return('C');} # なし

    if($next[$citeline+0]=~/ were not /){return('C');} # なし
    if($next[$citeline+1]=~/ were not /){return('C');} # なし
    if($next[$citeline+2]=~/ were not /){return('C');} # なし

    if($next[$citeline+0]=~/ it does not /){return('C');} # なし
    if($next[$citeline+1]=~/ it does not /){return('C');} # なし
    if($next[$citeline+2]=~/ it does not /){return('C');} # なし

    if($next[$citeline+0]=~/ may not /){return('C');} # なし
    if($next[$citeline+1]=~/ may not /){return('C');} # なし
    if($next[$citeline+2]=~/ may not /){return('C');} # なし

    if($next[$citeline+0]=~/ might not /){return('C');} # なし
    if($next[$citeline+1]=~/ might not /){return('C');} # なし
    if($next[$citeline+2]=~/ might not /){return('C');} # なし

    if($next[$citeline+0]=~/ will not /){return('C');} # なし
    if($next[$citeline+1]=~/ will not /){return('C');} # なし
    if($next[$citeline+2]=~/ will not /){return('C');} # なし

    if($next[$citeline+0]=~/ would not /){return('C');} # なし
    if($next[$citeline+1]=~/ would not /){return('C');} # なし
    if($next[$citeline+2]=~/ would not /){return('C');} # なし

    if($next[$citeline+0]=~/ wouldn't /){return('C');} # なし
    if($next[$citeline+1]=~/ wouldn't /){return('C');} # なし
    if($next[$citeline+2]=~/ wouldn't /){return('C');} # なし

#    if($next[$citeline+0]=~/cite.*? can ?n[o']t /){return('C');} # 1勝1敗
#    if($next[$citeline+1]=~/cite.*? can ?n[o']t /){return('C');} # 1敗
#    if($next[$citeline+2]=~/cite.*? can ?n[o']t /){return('C');} # 1敗

    if($next[$citeline+0]=~/ can ?not be/){return('C');} # なし
    if($next[$citeline+1]=~/ can ?not be/){return('C');} # なし
    if($next[$citeline+2]=~/ can ?not be/){return('C');} # なし

    if($next[$citeline+0]=~/ could not /){return('C');} # なし
    if($next[$citeline+1]=~/ could not /){return('C');} # なし
    if($next[$citeline+2]=~/ could not /){return('C');} # なし

    if($next[$citeline+0]=~/ couldn\'t /){return('C');} # なし
    if($next[$citeline+1]=~/ couldn\'t /){return('C');} # なし
    if($next[$citeline+2]=~/ couldn\'t /){return('C');} # なし

    if($next[$citeline+0]=~/ should not /){return('C');} # なし
    if($next[$citeline+1]=~/ should not /){return('C');} # なし
    if($next[$citeline+2]=~/ should not /){return('C');} # なし

    if($next[$citeline+0]=~/ need not /){return('C');} # なし
    if($next[$citeline+1]=~/ need not /){return('C');} # なし
    if($next[$citeline+2]=~/ need not /){return('C');} # なし

    if($next[$citeline+0]=~/ not always /){return('C');} # なし
    if($next[$citeline+1]=~/ not always /){return('C');} # なし
    if($next[$citeline+2]=~/ not always /){return('C');} # なし

    if($next[$citeline+0]=~/ not have /){return('C');} # なし
    if($next[$citeline+1]=~/ not have /){return('C');} # なし
    if($next[$citeline+2]=~/ not have /){return('C');} # なし

    if($next[$citeline+1]=~/ have not /){return('C');} # 1勝
    if($next[$citeline+2]=~/ have not /){return('C');} # なし

    if($next[$citeline+0]=~/ haven\'t /){return('C');} # なし
    if($next[$citeline+1]=~/ haven\'t /){return('C');} # なし
    if($next[$citeline+2]=~/ haven\'t /){return('C');} # なし

    if($next[$citeline+0]=~/ has not /){return('C');} # なし
    if($next[$citeline+1]=~/ has not /){return('C');} # なし
    if($next[$citeline+2]=~/ has not /){return('C');} # なし

    if($next[$citeline+0]=~/ hasn\'t /){return('C');} # 1勝
    if($next[$citeline+1]=~/ hasn\'t /){return('C');} # なし
    if($next[$citeline+2]=~/ hasn\'t /){return('C');} # なし

    if($next[$citeline+0]=~/ were not /){return('C');} # なし
    if($next[$citeline+1]=~/ were not /){return('C');} # なし
    if($next[$citeline+2]=~/ were not /){return('C');} #1勝

    if($next[$citeline+0]=~/that do not /){return('C');} # なし
    if($next[$citeline+1]=~/that do not /){return('C');} # なし
    if($next[$citeline+2]=~/that do not /){return('C');} # なし

    if($next[$citeline+0]=~/[Tt]hey do ?n[o']t /){return('C');} # 1勝
    if($next[$citeline+1]=~/[Tt]hey do ?n[o']t /){return('C');} # なし
    if($next[$citeline+2]=~/[Tt]hey do ?n[o']t /){return('C');} # なし

    if($next[$citeline+0]=~/[Hh]e does ?n[o']t /){return('C');} # なし
    if($next[$citeline+1]=~/[Hh]e does ?n[o']t /){return('C');} # なし
    if($next[$citeline+2]=~/[Hh]e does ?n[o']t /){return('C');} # なし

    if($next[$citeline+0]=~/[Ss]he does ?n[o']t /){return('C');} # なし
    if($next[$citeline+1]=~/[Ss]he does ?n[o']t /){return('C');} # なし
    if($next[$citeline+2]=~/[Ss]he does ?n[o']t /){return('C');} # なし

    if($next[$citeline+0]=~/ not require /){return('C');} # なし
    if($next[$citeline+1]=~/ not require /){return('C');} # なし
    if($next[$citeline+2]=~/ not require /){return('C');} # なし

    if($next[$citeline+0]=~/ not provide /){return('C');} # なし
    if($next[$citeline+1]=~/ not provide /){return('C');} # なし
    if($next[$citeline+2]=~/ not provide /){return('C');} # なし

    if($next[$citeline+0]=~/ not cover /){return('C');} # なし
    if($next[$citeline+1]=~/ not cover /){return('C');} # なし
    if($next[$citeline+2]=~/ not cover /){return('C');} # なし

    if($next[$citeline+0]=~/ not in effect /){return('C');}
    if($next[$citeline+1]=~/ not in effect /){return('C');}
    if($next[$citeline+2]=~/ not in effect /){return('C');}

    if($next[$citeline+0]=~/ more efficient than .*?cite/){return('C');}
    if($next[$citeline+1]=~/ more efficient than .*?cite/){return('C');}
    if($next[$citeline+2]=~/ more efficient than .*?cite/){return('C');}

    if($next[$citeline+0]=~/ not.*?enough /){return('C');} # 1勝

    if($next[$citeline+1]=~/ less studied/){return('C');} # 1勝
    if($next[$citeline+2]=~/ less studied/){return('C');} # なし

    if($next[$citeline+0]=~/[Ll]ittle influence/){return('C');} # なし
    if($next[$citeline+1]=~/[Ll]ittle influence/){return('C');} # 1勝
    if($next[$citeline+2]=~/[Ll]ittle influence/){return('C');} # なし


    if($next[$citeline+0]=~/ is too /){return('C');} # なし
    if($next[$citeline+1]=~/ is too /){return('C');} # なし
    if($next[$citeline+2]=~/ is too /){return('C');} # なし

    if($next[$citeline+0]=~/ more difficult /){return('C');} # なし
    if($next[$citeline+1]=~/ more difficult /){return('C');} # なし
    if($next[$citeline+2]=~/ more difficult /){return('C');} # なし

    if($next[$citeline+0]=~/ a difficult /){return('C');} # 1勝
    if($next[$citeline+1]=~/ a difficult /){return('C');}
    if($next[$citeline+2]=~/ a difficult /){return('C');}

    if($next[$citeline+1]=~/ difference[s]? between /){return('C');} # なし
    if($next[$citeline+2]=~/ difference[s]? between /){return('C');} # なし

    if($next[$citeline+0]=~/ the only /){return('C');} # なし
    if($next[$citeline+1]=~/ the only /){return('C');} # 1勝
    if($next[$citeline+2]=~/ the only /){return('C');} # なし

@base_cue=(
'available',
'[Ww]e adopt', 
'[Ww]e appl(y|ied)', 
'[Ww]e use',
'[Ww]e follow',
'[Ww]e select',
'[Ww]e opt',
'[Ww]e make use of',
'[Ww]e utilize',
'[Oo]ur .{,10} adopt',
'[Oo]ur .{,10} apply', 
'[Oo]ur .{,10} use',
'[Oo]ur .{,10} ma(k|d)e use of',
'[Oo]ur .{,10} utilize');

foreach $cu (@base_cue){
     if($next[$citeline]=~/$cu/ || $next[$citeline+1]=~/$cu/){return('B');}
}
    
return('O');
}
