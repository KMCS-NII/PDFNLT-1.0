$prefix=$ARGV[0] if $ARGV[0] ne '';
use Lingua::LanguageGuesser;
$path='/works/csisv12/akiko/acl_anthology';
for(glob("$path/SENT.tsv.out/$prefix*.tsv")){
    $_=~m#$path/SENT\.tsv\.out/(\w\d+-\d+)\.sent\.tsv#;
    $file=$1;
    if(open(F, '<:utf8', "$path/SENT.work/yoshida/eachfile/$file.sent_GarbledCount.tsv")){
	%nonEnglishChar=();%nonEnglishCharRatio=();
	while(<F>){
	    chomp;
	    ($section, $box, $line, $sentence, $num, $ratio)=split /\t/, $_;
	    $nonEnglishChar{$section}=$num unless $num eq '';
	    $nonEnglishCharRatio{$section}=$ratio/100 unless $ratio eq '';
	}
	close F;
    }
    open(G, '<:utf8', "$path/SENT.tsv.out/$file.sent.tsv") or die 'error: file cannot be opened';
    open(H, '>:utf8', "$path/SENT.tsv.out.integrated/$file.tsv");
    while(<G>){
	chomp;
	@line=split /\t/, $_;
	$ratio='';$ratio=$nonEnglishChar{$line[0]} if exists($nonEnglishChar{$line[0]});
	$num='';$num=$nonEnglishCharRatio{$line[0]} if exists($nonEnglishCharRatio{$line[0]});
	$guess=Lingua::LanguageGuesser->new({utf8, 'auto'}, $line[7]);
	$language=$guess->best_scoring();
	$output=join("\t", (@line[0..6], $ratio, $language, $line[7]));
	print H $output, "\n";
    }
    close G;
    close H;
}
