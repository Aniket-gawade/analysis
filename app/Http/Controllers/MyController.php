<?php

namespace App\Http\Controllers;

use App\Http\Services\RandomForestPrediction;
use App\Http\Services\Summary;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use App\Http\services\Prediction;
use App\Http\services\Training;
use App\Http\services\WordFrequency;
use App\Http\services\SpellandSarcasm;
use Antoineaugusti\LaravelSentimentAnalysis\SentimentAnalysis;
Use App\Http\services\Text;
use App\Http\services\Concordance;
use App\Http\services\Textspinner;
use Rubix\ML\Classifiers\RandomForest;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Datasets\Labeled;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MyController extends Controller
{
    //

    public function index(Request $request)
    {
        $input = $request->input('inp');
        // (new Training('topic.csv'))->train();
        $result1 = (new Prediction($input))->predict();

        $result2 = (new SentimentAnalysis)->scores($input);

        $result3 = (new WordFrequency)->getFrequency($input);

        $result4= (new Text($input))->predict();

        $inputArray=[
            $request->textc
        ];

        $intent_output=(new RandomForestPrediction('intent.csv',[$inputArray]))->predict();
        $result5 =  $intent_output[0];

        $resultArr = (new SpellandSarcasm)->predict($input);

        $result6 = $resultArr[0];

        $result7 = $resultArr[1];

        $result8 = (new Concordance)->getConcordance($input, $result3[1]);

        $result3 = $result3[0];
        return view('out',compact('result1','result2','result3','result4','result5','result6','result7', 'result8'));
    }

    public function result(Request $request)
    {
        $input = $request->input('inp');
        // (new Training('topic.csv'))->train();

        $topic = (new Prediction($input))->predict();

        //collocation code begins

        $subject = $input;
        $sentences= preg_split('/(?<!mr.|mrs.|dr.)(?<=[.?!;:])\s+/', $subject, -1, PREG_SPLIT_NO_EMPTY);

        $sentences_count=count($sentences);
        $token_sent=array();
        //echo $sentences_count;
        $bigram_count_array=array();
        $unigram=array();
        for($i=0;$i<$sentences_count;$i++)
        {
            $content=$sentences[$i];
            $content = strtolower($content);

            $words = array();
            $delim = " \n\t.,;-+*=()''_!@#$%^&*+~`{}[]|/:?1234567890\"\"";
            $tok = strtok($content, $delim);
            while ($tok !== false) {
                $words[] = $tok;
                $unigram[]=$tok;
                $tok = strtok($delim);
            }

            $total_words=count($words);
            $bigram=array();

            for($j=0; $j < $total_words-1; $j++) {
                $temp=array();
                array_push($temp,$words[$j],$words[$j+1]);
                array_push($bigram,$temp);
            }
            $bigram_count=count($bigram);
            array_push($bigram_count_array ,$bigram_count);
            array_push($token_sent ,$bigram);
        }

        //print_r($bigram_count_array);
        $token_sent_count=count($token_sent);
        $all_bigrams=array();
        $k=0;
        for($i=0;$i<$token_sent_count;$i++)
        {
            for($j=0; $j<$bigram_count_array[$k]; $j++)
            {   //echo "<br>[$i][$j][k=$bigram_count_array[$k]]";
                //print_r ($token_sent[$i][$j]);
                array_push($all_bigrams ,$token_sent[$i][$j]);
            }
            if($k<count($bigram_count_array))
                    $k++;
        }


        //print_r($all_bigrams);
        $all_bigrams_count=count($all_bigrams);


        //procedure to find unique bigrams begins here

        $unique_all_bigrams = array_map("unserialize", array_unique(array_map("serialize", $all_bigrams)));
        //print_r($unique_all_bigrams);
        //keys were missing so new array is made
        $new_unique_all_bigrams=array();

        //echo "<br><br>";

        foreach ($unique_all_bigrams as $unique_all_bigram)
        {
            array_push($new_unique_all_bigrams ,$unique_all_bigram);
        }
        //print_r($new_unique_all_bigrams);
        $new_unique_all_bigrams_count=count($new_unique_all_bigrams);
          //U have unique array and bigram array now you can count the frequency of bigrams
        //find freq for individual words as well.

        //-----bigram freq counting based on unique array begins
        $bigram_freq_array=array(); //stores frequency corresponding to $new_unique_all_bigrams

        for($i=0; $i<$new_unique_all_bigrams_count;$i++)
        {   $count=0;
            for($j=0;$j<$all_bigrams_count;$j++)
            {
                if(strcmp($all_bigrams[$j][0],$new_unique_all_bigrams[$i][0])==0 && strcmp($all_bigrams[$j][1],$new_unique_all_bigrams[$i][1])==0)
                {$count++;}
            }
            array_push($bigram_freq_array ,$count);
        }
        //print_r($bigram_freq_array);
//------counting frequency count of bigram done successfully

//procedure to find unigrams frequency

        $unigram_count=count($unigram);
        $unigram_key_value=array_count_values($unigram);
        //echo"<br><br>";
        //print_r($unigram_key_value);

//Procedure to find probability begins
        $probability_array=array();
        for($i=0;$i<$new_unique_all_bigrams_count;$i++)
        {   $bigram_probability=array();
            $prob=$bigram_freq_array[$i]/$unigram_key_value[$new_unique_all_bigrams[$i][0]];
            array_push($bigram_probability ,$new_unique_all_bigrams[$i],$prob);
            array_push($probability_array ,$bigram_probability);
        }
        //echo"<br><br>";
        //print_r($probability_array);
        //echo"<br><br>";
        //print_r(array_column($probability_array,1));

        array_multisort(array_column($probability_array, 1), SORT_DESC,$probability_array);
        //echo"<br><br>";
        //print_r($probability_array);

        $final_array = array();

        $PMI_array=array();
        for($i=0;$i<$new_unique_all_bigrams_count;$i++)
        {   $bigram_PMI = array();
            $PMI=(($bigram_freq_array[$i]/$all_bigrams_count)/($unigram_key_value[$new_unique_all_bigrams[$i][0]]/$unigram_count) * ($unigram_key_value[$new_unique_all_bigrams[$i][1]]/$unigram_count));
            array_push($bigram_PMI ,$new_unique_all_bigrams[$i],$PMI);
            //print_r($bigram_PMI);
            //echo"<br><br>";
            array_push($PMI_array,$bigram_PMI);
        }

        //print_r($PMI_array);
        //echo"<br><br>";
        array_multisort(array_column($PMI_array, 1), SORT_DESC,$PMI_array);
        //print_r($PMI_array);


        $final_array = array();
        $stopwords=array("0o", "0s", "3a", "3b", "3d", "6b", "6o", "a", "a1", "a2", "a3", "a4", "ab", "able", "about", "above", "abst", "ac", "accordance", "according", "accordingly", "across", "act", "actually", "ad", "added", "adj", "ae", "af", "affected", "affecting", "affects", "after", "afterwards", "ag", "again", "against", "ah", "ain", "ain't", "aj", "al", "all", "allow", "allows", "almost", "alone", "along", "already", "also", "although", "always", "am", "among", "amongst", "amoungst", "amount", "an", "and", "announce", "another", "any", "anybody", "anyhow", "anymore", "anyone", "anything", "anyway", "anyways", "anywhere", "ao", "ap", "apart", "apparently", "appear", "appreciate", "appropriate", "approximately", "ar", "are", "aren", "arent", "aren't", "arise", "around", "as", "a's", "aside", "ask", "asking", "associated", "at", "au", "auth", "av", "available", "aw", "away", "awfully", "ax", "ay", "az", "b", "b1", "b2", "b3", "ba", "back", "bc", "bd", "be", "became", "because", "become", "becomes", "becoming", "been", "before", "beforehand", "begin", "beginning", "beginnings", "begins", "behind", "being", "believe", "below", "beside", "besides", "best", "better", "between", "beyond", "bi", "bill", "biol", "bj", "bk", "bl", "bn", "both", "bottom", "bp", "br", "brief", "briefly", "bs", "bt", "bu", "but", "bx", "by", "c", "c1", "c2", "c3", "ca", "call", "came", "can", "cannot", "cant", "can't", "cause", "causes", "cc", "cd", "ce", "certain", "certainly", "cf", "cg", "ch", "changes", "ci", "cit", "cj", "cl", "clearly", "cm", "c'mon", "cn", "co", "com", "come", "comes", "con", "concerning", "consequently", "consider", "considering", "contain", "containing", "contains", "corresponding", "could", "couldn", "couldnt", "couldn't", "course", "cp", "cq", "cr", "cry", "cs", "c's", "ct", "cu", "currently", "cv", "cx", "cy", "cz", "d", "d2", "da", "date", "dc", "dd", "de", "definitely", "describe", "described", "despite", "detail", "df", "di", "did", "didn", "didn't", "different", "dj", "dk", "dl", "do", "does", "doesn", "doesn't", "doing", "don", "done", "don't", "down", "downwards", "dp", "dr", "ds", "dt", "du", "due", "during", "dx", "dy", "e", "e2", "e3", "ea", "each", "ec", "ed", "edu", "ee", "ef", "effect", "eg", "ei", "eight", "eighty", "either", "ej", "el", "eleven", "else", "elsewhere", "em", "empty", "en", "end", "ending", "enough", "entirely", "eo", "ep", "eq", "er", "es", "especially", "est", "et", "et-al", "etc", "eu", "ev", "even", "ever", "every", "everybody", "everyone", "everything", "everywhere", "ex", "exactly", "example", "except", "ey", "f", "f2", "fa", "far", "fc", "few", "ff", "fi", "fifteen", "fifth", "fify", "fill", "find", "fire", "first", "five", "fix", "fj", "fl", "fn", "fo", "followed", "following", "follows", "for", "former", "formerly", "forth", "forty", "found", "four", "fr", "from", "front", "fs", "ft", "fu", "full", "further", "furthermore", "fy", "g", "ga", "gave", "ge", "get", "gets", "getting", "gi", "give", "given", "gives", "giving", "gj", "gl", "go", "goes", "going", "gone", "got", "gotten", "gr", "greetings", "gs", "gy", "h", "h2", "h3", "had", "hadn", "hadn't", "happens", "hardly", "has", "hasn", "hasnt", "hasn't", "have", "haven", "haven't", "having", "he", "hed", "he'd", "he'll", "hello", "help", "hence", "her", "here", "hereafter", "hereby", "herein", "heres", "here's", "hereupon", "hers", "herself", "hes", "he's", "hh", "hi", "hid", "him", "himself", "his", "hither", "hj", "ho", "home", "hopefully", "how", "howbeit", "however", "how's", "hr", "hs", "http", "hu", "hundred", "hy", "i", "i2", "i3", "i4", "i6", "i7", "i8", "ia", "ib", "ibid", "ic", "id", "i'd", "ie", "if", "ig", "ignored", "ih", "ii", "ij", "il", "i'll", "im", "i'm", "immediate", "immediately", "importance", "important", "in", "inasmuch", "inc", "indeed", "index", "indicate", "indicated", "indicates", "information", "inner", "insofar", "instead", "interest", "into", "invention", "inward", "io", "ip", "iq", "ir", "is", "isn", "isn't", "it", "itd", "it'd", "it'll", "its", "it's", "itself", "iv", "i've", "ix", "iy", "iz", "j", "jj", "jr", "js", "jt", "ju", "just", "k", "ke", "keep", "keeps", "kept", "kg", "kj", "km", "know", "known", "knows", "ko", "l", "l2", "la", "largely", "last", "lately", "later", "latter", "latterly", "lb", "lc", "le", "least", "les", "less", "lest", "let", "lets", "let's", "lf", "like", "liked", "likely", "line", "little", "lj", "ll", "ll", "ln", "lo", "look", "looking", "looks", "los", "lr", "ls", "lt", "ltd", "m", "m2", "ma", "made", "mainly", "make", "makes", "many", "may", "maybe", "me", "mean", "means", "meantime", "meanwhile", "merely", "mg", "might", "mightn", "mightn't", "mill", "million", "mine", "miss", "ml", "mn", "mo", "more", "moreover", "most", "mostly", "move", "mr", "mrs", "ms", "mt", "mu", "much", "mug", "must", "mustn", "mustn't", "my", "myself", "n", "n2", "na", "name", "namely", "nay", "nc", "nd", "ne", "near", "nearly", "necessarily", "necessary", "need", "needn", "needn't", "needs", "neither", "never", "nevertheless", "new", "next", "ng", "ni", "nine", "ninety", "nj", "nl", "nn", "no", "nobody", "non", "none", "nonetheless", "noone", "nor", "normally", "nos", "not", "noted", "nothing", "novel", "now", "nowhere", "nr", "ns", "nt", "ny", "o", "oa", "ob", "obtain", "obtained", "obviously", "oc", "od", "of", "off", "often", "og", "oh", "oi", "oj", "ok", "okay", "ol", "old", "om", "omitted", "on", "once", "one", "ones", "only", "onto", "oo", "op", "oq", "or", "ord", "os", "ot", "other", "others", "otherwise", "ou", "ought", "our", "ours", "ourselves", "out", "outside", "over", "overall", "ow", "owing", "own", "ox", "oz", "p", "p1", "p2", "p3", "page", "pagecount", "pages", "par", "part", "particular", "particularly", "pas", "past", "pc", "pd", "pe", "per", "perhaps", "pf", "ph", "pi", "pj", "pk", "pl", "placed", "please", "plus", "pm", "pn", "po", "poorly", "possible", "possibly", "potentially", "pp", "pq", "pr", "predominantly", "present", "presumably", "previously", "primarily", "probably", "promptly", "proud", "provides", "ps", "pt", "pu", "put", "py", "q", "qj", "qu", "que", "quickly", "quite", "qv", "r", "r2", "ra", "ran", "rather", "rc", "rd", "re", "readily", "really", "reasonably", "recent", "recently", "ref", "refs", "regarding", "regardless", "regards", "related", "relatively", "research", "research-articl", "respectively", "resulted", "resulting", "results", "rf", "rh", "ri", "right", "rj", "rl", "rm", "rn", "ro", "rq", "rr", "rs", "rt", "ru", "run", "rv", "ry", "s", "s2", "sa", "said", "same", "saw", "say", "saying", "says", "sc", "sd", "se", "sec", "second", "secondly", "section", "see", "seeing", "seem", "seemed", "seeming", "seems", "seen", "self", "selves", "sensible", "sent", "serious", "seriously", "seven", "several", "sf", "shall", "shan", "shan't", "she", "shed", "she'd", "she'll", "shes", "she's", "should", "shouldn", "shouldn't", "should've", "show", "showed", "shown", "showns", "shows", "si", "side", "significant", "significantly", "similar", "similarly", "since", "sincere", "six", "sixty", "sj", "sl", "slightly", "sm", "sn", "so", "some", "somebody", "somehow", "someone", "somethan", "something", "sometime", "sometimes", "somewhat", "somewhere", "soon", "sorry", "sp", "specifically", "specified", "specify", "specifying", "sq", "sr", "ss", "st", "still", "stop", "strongly", "sub", "substantially", "successfully", "such", "sufficiently", "suggest", "sup", "sure", "sy", "system", "sz", "t", "t1", "t2", "t3", "take", "taken", "taking", "tb", "tc", "td", "te", "tell", "ten", "tends", "tf", "th", "than", "thank", "thanks", "thanx", "that", "that'll", "thats", "that's", "that've", "the", "their", "theirs", "them", "themselves", "then", "thence", "there", "thereafter", "thereby", "thered", "therefore", "therein", "there'll", "thereof", "therere", "theres", "there's", "thereto", "thereupon", "there've", "these", "they", "theyd", "they'd", "they'll", "theyre", "they're", "they've", "thickv", "thin", "think", "third", "this", "thorough", "thoroughly", "those", "thou", "though", "thoughh", "thousand", "three", "throug", "through", "throughout", "thru", "thus", "ti", "til", "tip", "tj", "tl", "tm", "tn", "to", "together", "too", "took", "top", "toward", "towards", "tp", "tq", "tr", "tried", "tries", "truly", "try", "trying", "ts", "t's", "tt", "tv", "twelve", "twenty", "twice", "two", "tx", "u", "u201d", "ue", "ui", "uj", "uk", "um", "un", "under", "unfortunately", "unless", "unlike", "unlikely", "until", "unto", "uo", "up", "upon", "ups", "ur", "us", "use", "used", "useful", "usefully", "usefulness", "uses", "using", "usually", "ut", "v", "va", "value", "various", "vd", "ve", "ve", "very", "via", "viz", "vj", "vo", "vol", "vols", "volumtype", "vq", "vs", "vt", "vu", "w", "wa", "want", "wants", "was", "wasn", "wasnt", "wasn't", "way", "we", "wed", "we'd", "welcome", "well", "we'll", "well-b", "went", "were", "we're", "weren", "werent", "weren't", "we've", "what", "whatever", "what'll", "whats", "what's", "when", "whence", "whenever", "when's", "where", "whereafter", "whereas", "whereby", "wherein", "wheres", "where's", "whereupon", "wherever", "whether", "which", "while", "whim", "whither", "who", "whod", "whoever", "whole", "who'll", "whom", "whomever", "whos", "who's", "whose", "why", "why's", "wi", "widely", "will", "willing", "wish", "with", "within", "without", "wo", "won", "wonder", "wont", "won't", "words", "world", "would", "wouldn", "wouldnt", "wouldn't", "www", "x", "x1", "x2", "x3", "xf", "xi", "xj", "xk", "xl", "xn", "xo", "xs", "xt", "xv", "xx", "y", "y2", "yes", "yet", "yj", "yl", "you", "youd", "you'd", "you'll", "your", "youre", "you're", "yours", "yourself", "yourselves", "you've", "yr", "ys", "yt", "z", "zero", "zi", "zz");
        $stopwords_count=count($stopwords);
        $withoutStopWords=array();

        for($i=0;$i<count($PMI_array);$i++)
        {   $flag=0;


            array_push($final_array, implode(" ",$probability_array[$i][0]));

            for($j=0;$j<$stopwords_count;$j++)
            {   if($PMI_array[$i][0][0] == $stopwords[$j] || $PMI_array[$i][0][1] == $stopwords[$j])
                {
                    $flag=1;
                    break;
                }
            }
            if($flag==0)
            {
                array_push($withoutStopWords, array($probability_array[$i][0][0],$probability_array[$i][0][1]));
            }
        }
        //print_r($probability_array);
        //print_r($withoutStopWords);
        //echo"<br><br>";
        //print_r($final_array);
        $implode_final_array=implode($final_array);



        //collocation code ends


        $inputArray=[
            $implode_final_array
        ];
        $intent_output=(new RandomForestPrediction('intent.csv',[$inputArray]))->predict();
        $sentiment =  $intent_output[0];

        $resultArr = (new SpellandSarcasm)->predict($input);

        $result6 = $resultArr[0];

        $sarcasm = $resultArr[1];

        $summary = (new Summary)->getSummary($input);

        $summary = str_replace(array("\n", "\r"), ' ', $summary);

        $summary = (new Textspinner)->getText($summary);

        return view('output1',compact('topic','sentiment','sarcasm','summary'));

    }


}
