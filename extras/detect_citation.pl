#!/usr/bin/env perl
# あ
#
# not solved
# A00-1013: Eric Brill's rule-based word tagger (1992,1994a, 1994b)    # 誰々のツール
# C92-1040: Conceptual Semantics Jac90, Jac91 to fit the CCG paradigm. # 括弧がない
# C00-1010: Bod 1993, 98                                               # 2番目以降が2桁
# C02-1010: Huang, J. and Choi, K. (2000)                              # イニシャルを使う
#
use strict;
use warnings;
use utf8;
use XML::Simple qw(:strict);
use HTML::Entities;
use Data::Dumper;
binmode STDIN, ":utf8";
binmode STDOUT, ":utf8";

my $person = qr/(?:van |de |\p{Lu}\p{Latin}+)/;
my $year = qr/(?:19\d\d|20\d\d|[6789]\d)[abcde]?|forthcoming/;
my $head_phrase = qr/(?:eg\.|e\.g\.,?|see, e\.g\.,|cf\.|see|See) /;
my $foot_phrase = qr/(?:inter alia| |,|\.)+/;

my $file = $ARGV[0] or die "usage: $0 xhtml_file [debug]";
my $debug = $ARGV[1];

main($file);

exit;

sub main {
    my $file = shift;
    my $xmlsimple = XML::Simple->new();
    my $xml = $xmlsimple->XMLin($file, KeyAttr => [], ForceArray => 1);
    my $pages = $xml->{head}->[0]->{pages}->[0]->{page};
    my $body = $xml->{body}->[0];
    my $sections = $body->{div};

    my (@body_text, @reference_text);
    foreach my $r1 ( @$sections ) {
        my $class = $r1->{class};
        my $data_name1 = $r1->{'data-name'};
        foreach my $r2 ( @{$r1->{div}} ) {
            my $data_name2 = $r2->{'data-name'};
            if ( defined $r2->{p} ) {
                foreach my $p ( @{$r2->{p}} ) {
                    my $ref = {
                        id => $p->{'id'},
                        text => $p->{'data-text'},
                        node => $p
                    };
                    decode_entities($ref->{text});
                    if ( $data_name2 eq 'Reference' ) {
                        $ref->{is_reference} = 1;
                        push @reference_text, $ref;
                    } else {
                        push @body_text, $ref;
                    }
                }
            }
        }
    }

    my $ref_key = extract_references(\@reference_text);

    my $context = {
        body_text => \@body_text,
        reference_text => \@reference_text,
        ref_key => $ref_key,
        matched => {},
        match_context => [],
        match_id => 0
    };

    search($context);

    my $output = cite_mark_range($context);

    if ( $debug ) {
        print join("\n", @$output), "\n";
    } else {
        my $xmlstring = $xmlsimple->XMLout($xml, KeyAttr => [], KeepRoot => 1, RootName => 'html', XMLDecl => "<?xml version=\"1.0\"?>\n<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">");
        $xmlstring =~ s/(<body.+?<\/body>)(.+?)(<head.+?<\/head>)/$3$2$1/s;
        $xmlstring =~ s/(<\w+)( .+?)( id=".+?")/$1$3$2/g;
        print $xmlstring;
    }
}

sub extract_references {
    my $reference_text = shift;
    my %ref_key;
    foreach my $ref ( @$reference_text ) {
        if ( $ref->{text} =~ /^\[([^\[]+?)\]/ ) {
            my $key = $1;
            $key =~ tr/ //d;
            $ref_key{$key} = $ref->{id};
        } elsif ( $ref->{text} =~ /^(\d\d?)\.\s?\p{Lu}/ ) {
            $ref_key{$1} = $ref->{id};
        }
    }
    return \%ref_key;
}

sub search {
    my $context = shift;
    foreach my $ref ( @{$context->{body_text}} ) {
        ## type1
        $ref->{text} =~ s/(?:\((?:$head_phrase)?($person[^()]*$year)(?:$foot_phrase)?\)) |
                   (?:\[(?:$head_phrase)?($person[^\[\]]*$year)(?:$foot_phrase)?\])/replace1($1, $2, $context)/exg;
        ## type2
        $ref->{text} =~ s/($person(?: and $person|
                    [, ]+et[\. ]al)?)[\., ]*(?:['’]s )?
                    [\(\[]((?:$year|[;, ])+)[\)\]]/replace2($&, $1, $2, $context)/exg;
        ## type3
        $ref->{text} =~ s/\[($person)[, ]+(\d\d?)\]/replace3($&, $1, $2, $context)/eg;
        ## type4
        if ( keys %{$context->{ref_key}} ) {
            $ref->{text} =~ s/\[([^\[]+?)\]/replace4($&, $1, $context)/eg;
        }
    }
}

sub replace1 {
    my $all = $_[0] || $_[1];
    my $context = $_[2];
    my $tmp_all = $all =~ s/\s//gr;
    my $split = $tmp_all =~ /$year(\W)\D/ ? $1 : ';';
    my @cites = split(/(?<=\d\d|\d[abcde])\s?$split\s*/, $all);
    if ( $tmp_all =~ /$year[,;]$year/ ) {
        @cites = split_year(@cites);
    }
    my @cids;
    foreach my $str ( @cites ) {
        next if $str =~ /^\d/;
        my $match = detect_reference($str, $context);
        next if ! @$match;
        my $cid = $match->[0]->{'cid'};
        push @cids, $cid;
        $context->{matched}->{$cid}++;
    }
    if ( @cids ) {
        my $mid = ++($context->{match_id});
        $context->{match_context}->[$mid] = {
            cids => \@cids,
            context => $all,
            type => 1
        };
        return "<refer cite=\"$mid\">";
    } else {
        return $all;
    }
}

sub replace2 {
    my ($all, $names, $years, $context) = @_;
    my @cids;
    foreach my $year ( split(/[;, ]+/, $years) ) {
        my $str = $names . ' ' . $year;
        my $match = detect_reference($str, $context);
        next if ! @$match;
        my $cid = $match->[0]->{'cid'};
        push @cids, $cid;
        $context->{matched}->{$cid}++;
    }
    if ( @cids ) {
        my $mid = ++($context->{match_id});
        $context->{match_context}->[$mid] = {
            cids => \@cids,
            context => $all,
            type => 2
        };
        return "<refer cite=\"$mid\">";
    } else {
        return $all;
    }
}

sub replace3 {
    my ($all, $m1, $m2, $context) = @_;
    my $str = $m2 . ' ' . $m1;
    my $match = detect_reference($str, $context);
    if ( @$match ) {
        my $cid = $match->[0]->{'cid'};
        my $mid = ++($context->{match_id});
        $context->{matched}->{$cid}++;
        $context->{match_context}->[$mid] = {
            cids => [$cid],
            context => $all,
            type => 3
        };
        return "<refer cite=\"$mid\">";
    } else {
        return $all;
    }
}

sub replace4 {
    my ($all, $hit, $context) = @_;
    my $tmp_hit = $hit =~ s/\s//gr;
    my @cids;
    foreach my $key ( split(/,/, $tmp_hit) ) {
        $key =~ tr/ //d;
        my $cid = $context->{ref_key}->{$key};
        next if ! $cid;
        push @cids, $cid;
        $context->{matched}->{$cid}++;
    }
    if ( @cids ) {
        my $mid = ++($context->{match_id});
        $context->{match_context}->[$mid] = {
            cids => \@cids,
            context => $all,
            type => 4
        };
        return "<refer cite=\"$mid\">";
    } else {
        return $all;
    }
}

sub detect_reference {
    my ($str, $context) = @_;
    $str =~ s/($year)/ $1/g;
    my @elem = grep { ! /^(?:and|et|al|others)$/ } split(/[,\. ]+/, $str);
    my $regex = join('\W.*?', map { quotemeta($_) } @elem);
    my @match;
    foreach my $ref ( @{$context->{reference_text}} ) {
        if ( $ref->{text} =~ /$regex/i ) {
            push @match, {
                cid   => $ref->{id},
                start => length($`),
                total => length($&)
            };
        }
    }
    if ( @match > 1 ) {
        if ( $str =~ /et[. ]al|others/ ) {
            @match = sort { $a->{'start'} <=> $b->{'start'} or
                            $b->{'total'} <=> $a->{'total'} } @match;
        } else {
            @match = sort { $a->{'start'} <=> $b->{'start'} or
                            $a->{'total'} <=> $b->{'total'} } @match;
        }
    }
    return \@match;
}

sub split_year {
    my @cites = @_;
    my @out;
    foreach my $cite ( @cites ) {
        if ( $cite =~ /^(\D+?)($year(?:[,; ]+$year)+)$/ ) {
            my ($names, $years) = ($1, $2);
            push @out, $names . ' ' . $_ for split(/[, ]+/, $years);
        } else {
            push @out, $cite;
        }
    }
    return @out;
}

sub cite_mark_range {
    my ($context) = @_;
    my %cid2phrase;
    foreach my $ref ( @{$context->{body_text}} ) {
        my @spans = @{$ref->{node}->{span}};
        my @match_span;
        my $paragraph = IdentifyType::identify_citation_type($ref->{text});
        while ( $paragraph =~ /<refer cite="(.+?)" type="(.*?)" cue="(.*?)">/g ) {
            my ($match_id, $type, $cue) = ($1, $2, $3);
            my $m = $context->{match_context}->[$match_id];
            my $hit = 'no hit';
            my @phrase = grep { $_ ne ' ' } split(/\t+/, ($m->{'context'} =~ s/([ ,.\(\)\[\];:])/\t$1\t/gr));
            my $last_year_idx = $phrase[$#phrase] =~ /[\)\]]/ ? $#phrase - 1 : $#phrase;
            if ( $m->{type} == 3 or $m->{type} == 4 ) {
                shift @phrase;
                $last_year_idx = $#phrase;
            }
            for(my $i=0; $i<@spans; ++$i) {
                if ( defined $spans[$i]->{content} and
                     defined $spans[$i+$last_year_idx]->{content} and
                     $spans[$i]->{content} eq $phrase[0] and
                     $spans[$i+$last_year_idx]->{content} eq $phrase[$last_year_idx] and
                     not defined $match_span[$i]
                    ) {
                    $spans[$i]->{'data-cite-id'} = join(',', @{$m->{'cids'}});
                    $spans[$i]->{'data-cite-end'} = $spans[$i+scalar(@phrase)-1]->{id};
                    $spans[$i]->{'data-cite-type'} = $type;
                    $spans[$i]->{'data-cite-type-cue'} = $cue;
                    $hit = join(" ", map { $_->{content} } @spans[$i .. $i+scalar(@phrase)-1]);
                    $match_span[$_]++ for $i .. $i+scalar(@phrase)-1;
                    last;
                }
            }
            if ( $debug ) {
                foreach my $cid ( @{$m->{'cids'}} ) {
                    push @{$cid2phrase{$cid}}, {
                        phrase => $m->{context},
                        mtype => $m->{type},
                        hit => $hit eq 'no hit' ? 'x' : 'o',
                        type => $type,
                        cue => $cue
                    }
                }
            }
        }
    }

    my @output;
    if ( $debug ) {
        foreach my $ref ( @{$context->{reference_text}} ) {
            if ( $ref->{is_reference} ) {
                my $has_cite_mark = $context->{matched}->{$ref->{id}} ? 'o' : 'x';
                push @output, sprintf "%s\ttext=%s\t%s", $ref->{id}, $has_cite_mark, $ref->{text};
                push @output, "\t" . $_ for map { sprintf("mtype=%s\tspan=%s\ttype=%s\tcue=%s\t%s", $_->{mtype}, $_->{hit}, $_->{type}, $_->{cue}, $_->{phrase}) } @{$cid2phrase{$ref->{id}}};
                push @output, '';
            }
        }
    }
    return \@output;
}

sub dumprint {
    my $ref = shift;
    my $dump = Dumper $ref;
    $dump =~ s/\\x\{([0-9a-z]+)\}/chr(hex($1))/ge;
    print $dump;
}


#
# HTMLファイルを入力とし、引用タイプを付与する。
#                                    2016.9.15 Hidetsugu Nanba
#                             Modify 2016.9.26 Takeshi Abekawa
#
package IdentifyType;
use strict;
use warnings;
use utf8;

our @base_cue = (
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
    '[Oo]ur .{,10} utilize'
);

sub identify_citation_type{ # 引用タイプの判定
    my $paragraph = shift;
    my @sentences = split(/(?<=\.) (?=\p{Lu})/, $paragraph);
    push @sentences, '' for 1 .. 5;
    my @result;
    for(my $i=0; $i<@sentences-5; ++$i ) {
        next if $sentences[$i] !~ /<refer/;
        my ($type, $cue) = rule_type_new_cue(\@sentences, $i);
        $cue =~ s/^[,. ]+//;
        $cue =~ s/[,. ]+$//;
        $sentences[$i] =~ s/<refer cite="(.+?)">/<refer cite="$1" type="$type" cue="$cue">/g;
    }
    pop @sentences for 1 .. 5;
    return join(" ", @sentences);
}

sub rule_type_new_cue {
    my ($sent, $citeline) = @_;

    if ($sent->[$citeline+0]=~/[Aa]lthough the /) {
        return('C', $&);
    }                                                        # 1勝
    if ($sent->[$citeline+1]=~/[Aa]lthough the /) {
        return('C', $&);
    }                                                        # なし
    if ($sent->[$citeline+2]=~/[Aa]lthough the /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/\, ?although /) {
        return('C', $&);
    }                                                     # なし
    if ($sent->[$citeline+1]=~/\, ?although /) {
        return('C', $&);
    }                                                     # 1敗
    if ($sent->[$citeline+2]=~/\, ?although /) {
        return('C', $&);
    }                           # 1勝

    if ($sent->[$citeline+0]=~/Though[ ,]/) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/Though[ ,]/) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+2]=~/Though[ ,]/) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/however\,[^<]*?our /) {
        return('C', $&);
    }                                                        # 1勝
    if ($sent->[$citeline+1]=~/however\,[^<]*?our /) {
        return('C', $&);
    }                                                        # なし
    if ($sent->[$citeline+2]=~/however\,[^<]*?our /) {
        return('C', $&);
    }                                                        # 1勝
    if ($sent->[$citeline+3]=~/however\,[^<]*?our /) {
        return('C', $&);
    }                                                        # なし
    if ($sent->[$citeline+4]=~/however\,[^<]*?our /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/however\,[^<]*? they /) {
        return('C', $&);
    }                                                          # なし
    if ($sent->[$citeline+1]=~/however\,[^<]*? they /) {
        return('C', $&);
    }                                                          # 1勝
    if ($sent->[$citeline+2]=~/however\,[^<]*? they /) {
        return('C', $&);
    }                                                          # なし
    if ($sent->[$citeline+3]=~/however\,[^<]*? they /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/however\,[^<]*? not /) {
        return('C', $&);
    }                                                         # 1勝
    if ($sent->[$citeline+1]=~/however\,[^<]*? not /) {
        return('C', $&);
    }                                                         # 1勝
    if ($sent->[$citeline+2]=~/however\,[^<]*? not /) {
        return('C', $&);
    }                                                         # なし
    if ($sent->[$citeline+3]=~/however\,[^<]*? not /) {
        return('C', $&);
    }                                                         # なし
    if ($sent->[$citeline+4]=~/however\,[^<]*? not /) {
        return('C', $&);
    }                                                         # なし
    if ($sent->[$citeline+5]=~/however\,[^<]*? not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+1]=~/However\,?/) {
        return('C', $&);
    }                                                  # 6勝
    if ($sent->[$citeline+2]=~/However\,?/) {
        return('C', $&);
    }                                                  # 4勝
    if ($sent->[$citeline+3]=~/However\,?/) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+4]=~/However\,?/) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+5]=~/However\,?/) {
        return('C', $&);
    }                           # 1勝

    if ($sent->[$citeline+1]=~/however\, *the/) {
        return('C', $&);
    }                                                      # 1勝
    if ($sent->[$citeline+2]=~/however\, *the/) {
        return('C', $&);
    }                                                      # 1勝
    if ($sent->[$citeline+3]=~/however\, *the/) {
        return('C', $&);
    }                                                      # なし
    if ($sent->[$citeline+4]=~/however\, *the/) {
        return('C', $&);
    }                                                      # なし
    if ($sent->[$citeline+5]=~/however\, *the/) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+1]=~/But /) {
        return('C', $&);
    }                                            # なし
    if ($sent->[$citeline+2]=~/But /) {
        return('C', $&);
    }                                            # 1勝
    if ($sent->[$citeline+3]=~/But /) {
        return('C', $&);
    }                                            # なし
    if ($sent->[$citeline+4]=~/But /) {
        return('C', $&);
    }                                            # なし
    if ($sent->[$citeline+5]=~/But /) {
        return('C', $&);
    }                           # 1勝

    if ($sent->[$citeline+0]=~/but a /) {
        return('C', $&);
    }                                              # なし
    if ($sent->[$citeline+1]=~/but a /) {
        return('C', $&);
    }                                              # なし
    if ($sent->[$citeline+2]=~/but a /) {
        return('C', $&);
    }                           # なし


    if ($sent->[$citeline+0]=~/but the /) {
        return('C', $&);
    }                                                # 1勝1敗
    if ($sent->[$citeline+1]=~/but the /) {
        return('C', $&);
    }                                                # なし
    if ($sent->[$citeline+2]=~/but the /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/but it /) {
        return('C', $&);
    }                                               # 1勝1敗
    if ($sent->[$citeline+1]=~/but it /) {
        return('C', $&);
    }                                               # なし
    if ($sent->[$citeline+2]=~/but it /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/but is /) {
        return('C', $&);
    }                                               # なし
    if ($sent->[$citeline+1]=~/but is /) {
        return('C', $&);
    }                                               # なし
    if ($sent->[$citeline+2]=~/but is /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/but are /) {
        return('C', $&);
    }                                                # なし
    if ($sent->[$citeline+1]=~/but are /) {
        return('C', $&);
    }                                                # なし
    if ($sent->[$citeline+2]=~/but are /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/but rather /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+1]=~/but rather /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/but rather /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/but no /) {
        return('C', $&);
    }                                               # 1勝
    if ($sent->[$citeline+1]=~/but no /) {
        return('C', $&);
    }                                               # なし
    if ($sent->[$citeline+2]=~/but no /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Bb]ut they/) {
        return('C', $&);
    }                                                   # 1勝
    if ($sent->[$citeline+1]=~/[Bb]ut they/) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/[Bb]ut they/) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Bb]ut their/) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+1]=~/[Bb]ut their/) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+2]=~/[Bb]ut their/) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Bb]ut he /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/[Bb]ut he /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+2]=~/[Bb]ut he /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Bb]ut his /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+1]=~/[Bb]ut his /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/[Bb]ut his /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Bb]ut she /) {
        return('C', $&);
    }                                                   # 1勝
    if ($sent->[$citeline+1]=~/[Bb]ut she /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/[Bb]ut she /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Bb]ut her /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+1]=~/[Bb]ut her /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/[Bb]ut her /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Bb]ut it/) {
        return('C', $&);
    }                                                 # 1勝
    if ($sent->[$citeline+1]=~/[Bb]ut it/) {
        return('C', $&);
    }                                                 # なし
    if ($sent->[$citeline+2]=~/[Bb]ut it/) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Bb]ut instead/) {
        return('C', $&);
    }                                                      # なし
    if ($sent->[$citeline+1]=~/[Bb]ut instead/) {
        return('C', $&);
    }                                                      # なし
    if ($sent->[$citeline+2]=~/[Bb]ut instead/) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/Instead ?\,/) {
        return('C', $&);
    }
    if ($sent->[$citeline+1]=~/Instead ?\,/) {
        return('C', $&);
    }
    if ($sent->[$citeline+2]=~/Instead ?\,/) {
        return('C', $&);
    }

    if ($sent->[$citeline-1]=~/In spite of /) {
        return('C', $&);
    }                                                    # 1勝
    if ($sent->[$citeline+0]=~/In spite of /) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+1]=~/In spite of /) {
        return('C', $&);
    }                                                    # 1勝
    if ($sent->[$citeline+2]=~/In spite of /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+1]=~/ does not /) {
        return('C', $&);
    }
    if ($sent->[$citeline+2]=~/ does not /) {
        return('C', $&);
    }

    if ($sent->[$citeline+0]=~/ did not /) {
        return('C', $&);
    }
    if ($sent->[$citeline+1]=~/ did not /) {
        return('C', $&);
    }
    if ($sent->[$citeline+2]=~/ did not /) {
        return('C', $&);
    }

    if ($sent->[$citeline+0]=~/ that is not /) {
        return('C', $&);
    }
    if ($sent->[$citeline+1]=~/ that is not /) {
        return('C', $&);
    }
    if ($sent->[$citeline+2]=~/ that is not /) {
        return('C', $&);
    }

    if ($sent->[$citeline+0]=~/ not be /) {
        return('C', $&);
    }                                                # 1勝1負
    if ($sent->[$citeline+1]=~/ not be /) {
        return('C', $&);
    }                                                # なし
    if ($sent->[$citeline+2]=~/ not be /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ it is not /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+1]=~/ it is not /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/ it is not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ this is not /) {
        return('C', $&);
    }                                                     # なし
    if ($sent->[$citeline+1]=~/ this is not /) {
        return('C', $&);
    }                                                     # なし
    if ($sent->[$citeline+2]=~/ this is not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ was not /) {
        return('C', $&);
    }                                                 # なし
    if ($sent->[$citeline+1]=~/ was not /) {
        return('C', $&);
    }                                                 # なし
    if ($sent->[$citeline+2]=~/ was not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ were not /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/ were not /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+2]=~/ were not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ it does not /) {
        return('C', $&);
    }                                                     # なし
    if ($sent->[$citeline+1]=~/ it does not /) {
        return('C', $&);
    }                                                     # なし
    if ($sent->[$citeline+2]=~/ it does not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ may not /) {
        return('C', $&);
    }                                                 # なし
    if ($sent->[$citeline+1]=~/ may not /) {
        return('C', $&);
    }                                                 # なし
    if ($sent->[$citeline+2]=~/ may not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ might not /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+1]=~/ might not /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/ might not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ will not /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/ will not /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+2]=~/ will not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ would not /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+1]=~/ would not /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/ would not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ wouldn't /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/ wouldn't /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+2]=~/ wouldn't /) {
        return('C', $&);
    }                           # なし

    #    if($sent->[$citeline+0]=~/cite.*? can ?n[o']t /){return('C', $&);} # 1勝1敗
    #    if($sent->[$citeline+1]=~/cite.*? can ?n[o']t /){return('C', $&);} # 1敗
    #    if($sent->[$citeline+2]=~/cite.*? can ?n[o']t /){return('C', $&);} # 1敗

    if ($sent->[$citeline+0]=~/ can ?not be/) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+1]=~/ can ?not be/) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+2]=~/ can ?not be/) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ could not /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+1]=~/ could not /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/ could not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ couldn\'t /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+1]=~/ couldn\'t /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/ couldn\'t /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ should not /) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+1]=~/ should not /) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+2]=~/ should not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ need not /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/ need not /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+2]=~/ need not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ not always /) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+1]=~/ not always /) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+2]=~/ not always /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ not have /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/ not have /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+2]=~/ not have /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+1]=~/ have not /) {
        return('C', $&);
    }                                                  # 1勝
    if ($sent->[$citeline+2]=~/ have not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ haven\'t /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/ haven\'t /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+2]=~/ haven\'t /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ has not /) {
        return('C', $&);
    }                                                 # なし
    if ($sent->[$citeline+1]=~/ has not /) {
        return('C', $&);
    }                                                 # なし
    if ($sent->[$citeline+2]=~/ has not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ hasn\'t /) {
        return('C', $&);
    }                                                 # 1勝
    if ($sent->[$citeline+1]=~/ hasn\'t /) {
        return('C', $&);
    }                                                 # なし
    if ($sent->[$citeline+2]=~/ hasn\'t /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ were not /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/ were not /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+2]=~/ were not /) {
        return('C', $&);
    }                           #1勝

    if ($sent->[$citeline+0]=~/that do not /) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+1]=~/that do not /) {
        return('C', $&);
    }                                                    # なし
    if ($sent->[$citeline+2]=~/that do not /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Tt]hey do ?n[o']t /) {
        return('C', $&);
    }                                                           # 1勝
    if ($sent->[$citeline+1]=~/[Tt]hey do ?n[o']t /) {
        return('C', $&);
    }                                                           # なし
    if ($sent->[$citeline+2]=~/[Tt]hey do ?n[o']t /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Hh]e does ?n[o']t /) {
        return('C', $&);
    }                                                           # なし
    if ($sent->[$citeline+1]=~/[Hh]e does ?n[o']t /) {
        return('C', $&);
    }                                                           # なし
    if ($sent->[$citeline+2]=~/[Hh]e does ?n[o']t /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Ss]he does ?n[o']t /) {
        return('C', $&);
    }                           # なし
    if ($sent->[$citeline+1]=~/[Ss]he does ?n[o']t /) {
        return('C', $&);
    }                           # なし
    if ($sent->[$citeline+2]=~/[Ss]he does ?n[o']t /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ not require /) {
        return('C', $&);
    }                                                     # なし
    if ($sent->[$citeline+1]=~/ not require /) {
        return('C', $&);
    }                                                     # なし
    if ($sent->[$citeline+2]=~/ not require /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ not provide /) {
        return('C', $&);
    }                                                     # なし
    if ($sent->[$citeline+1]=~/ not provide /) {
        return('C', $&);
    }                                                     # なし
    if ($sent->[$citeline+2]=~/ not provide /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ not cover /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+1]=~/ not cover /) {
        return('C', $&);
    }                                                   # なし
    if ($sent->[$citeline+2]=~/ not cover /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ not in effect /) {
        return('C', $&);
    }
    if ($sent->[$citeline+1]=~/ not in effect /) {
        return('C', $&);
    }
    if ($sent->[$citeline+2]=~/ not in effect /) {
        return('C', $&);
    }

    if ($sent->[$citeline+0]=~/ more efficient than [^<]*?cite/) {
        return('C', $&);
    }
    if ($sent->[$citeline+1]=~/ more efficient than [^<]*?cite/) {
        return('C', $&);
    }
    if ($sent->[$citeline+2]=~/ more efficient than [^<]*?cite/) {
        return('C', $&);
    }

    if ($sent->[$citeline+0]=~/ not[^<]*?enough /) {
        return('C', $&);
    }                           # 1勝

    if ($sent->[$citeline+1]=~/ less studied/) {
        return('C', $&);
    }                                                     # 1勝
    if ($sent->[$citeline+2]=~/ less studied/) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/[Ll]ittle influence/) {
        return('C', $&);
    }                                                           # なし
    if ($sent->[$citeline+1]=~/[Ll]ittle influence/) {
        return('C', $&);
    }                                                           # 1勝
    if ($sent->[$citeline+2]=~/[Ll]ittle influence/) {
        return('C', $&);
    }                           # なし


    if ($sent->[$citeline+0]=~/ is too /) {
        return('C', $&);
    }                                                # なし
    if ($sent->[$citeline+1]=~/ is too /) {
        return('C', $&);
    }                                                # なし
    if ($sent->[$citeline+2]=~/ is too /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ more difficult /) {
        return('C', $&);
    }                                                        # なし
    if ($sent->[$citeline+1]=~/ more difficult /) {
        return('C', $&);
    }                                                        # なし
    if ($sent->[$citeline+2]=~/ more difficult /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ a difficult /) {
        return('C', $&);
    }                           # 1勝
    if ($sent->[$citeline+1]=~/ a difficult /) {
        return('C', $&);
    }
    if ($sent->[$citeline+2]=~/ a difficult /) {
        return('C', $&);
    }

    if ($sent->[$citeline+1]=~/ difference[s]? between /) {
        return('C', $&);
    }                           # なし
    if ($sent->[$citeline+2]=~/ difference[s]? between /) {
        return('C', $&);
    }                           # なし

    if ($sent->[$citeline+0]=~/ the only /) {
        return('C', $&);
    }                                                  # なし
    if ($sent->[$citeline+1]=~/ the only /) {
        return('C', $&);
    }                                                  # 1勝
    if ($sent->[$citeline+2]=~/ the only /) {
        return('C', $&);
    }                           # なし

    my @base_cue = (
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
    '[Oo]ur .{,10} utilize'
   );

    foreach my $cu (@base_cue) {
        if ($sent->[$citeline]=~/$cu/ || $sent->[$citeline+1]=~/$cu/) {
            return('B', $&);
        }
    }

    return('O', '');
}
