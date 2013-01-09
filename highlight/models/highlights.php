<?

class Highlights extends CI_Model {

    private $idlength = 8;
    private $availablechars = '0123456789abcdefghijklmnopqrstuvwxyz';
    private $attempt = 0;
    private $threshold = 5;
    private $words = 2;
    private $innerHTML = '';
    private $myCount = 0;
    private $currentTag = '';
    private $masterCount = 0;

    private $highlightstart = '';
    private $highlightend = '';
    private $bgcolour = '#FFB3F9'; // can set defaults here
    private $colour = '#000000';

    function __construct() {
        parent::__construct();
    }

    function returnID() {
        return $this->generateID();
    }

    private function generateID() {
        $id = false;
        if (strlen($this->availablechars) > 0) {
            for($i=0; $i < $this->idlength; $i++) {
                $id .= substr($this->availablechars, rand(0,(strlen($this->availablechars)-1)), 1);
            }
        }
        // check exists
        $result = $this->db->from('highlights')->where(array('hid'=>$id))->get()->row();
        // if ID exists, generate another (until we find a unique)
        if ($result) {
            if ($this->attempt > $this->threshold) return false;
            $this->attempt++;
            $id = $this->generateID();
        }
        return $id;
    }

    function save($data) {
        $data['created_date'] = (isset($data['created_date']) && (is_numeric($data['created_date']))) ? $data['created_date'] : date('U');
        $selectedtext = $data['rawdomtext'];
        $str = str_ireplace('><','>####<', $selectedtext);
        $arr = explode('####', $str);
        // cheese is completely clean, bacon is just the selected string text minus the browser-added tags
        $cheese = '';
        $bacon = '';
        $c = 1;
        foreach ($arr as $el) {
            if ($c==count($arr)) {
                $s = strrev($el);
                $cheese .= preg_replace('/\n/', '', strrev(substr($s, (strpos($s, '<')+1))));
                $bacon .= strrev(substr($s, (strpos($s, '<')+1)));
            } else {
                $cheese .= preg_replace('/\n/', '', substr($el, (strpos($el, '>')+1)));
                $bacon .= substr($el, (strpos($el, '>')+1));
            }
            $c++;
        }
        $data['selectedtext'] = $cheese;
        $data['rawtext'] = $bacon;
        return $this->db->insert('highlights', $data);
    }

    function getViewdata($id) {
        $where = array('hid'=>$id);
        $result = $this->db->from('highlights')->where($where)->get()->row();
        
        $rd = array(); // return data
        $rd['id'] = $id;
        $rd['url'] = $result->url;
        $rd['text'] = $result->selectedtext;
        $rd['rawtext'] = $result->rawtext;
        $rd['rawdomtext'] = $result->rawdomtext;
        $rd['createddate'] = $result->created_date;
        $w = explode(' ', $result->selectedtext, 5); // # words required+1
        $ws = '';
        for ($i=0; $i < $this->words; $i++) {
            $ws .= isset($w[$i]) ? $w[$i] : '';
            if ($i!=($this->words-1)&&(isset($w[$i]))) $ws .= ' ';
        }
        $rd['firstword'] = trim($ws);
        $w = explode(' ', strrev($result->selectedtext), 5);
        $lw = ''; //trim(@$w[0] . ' ' . @$w[1] . ' ' . @$w[2] . ' ' . @$w[3]);
        for ($i=0; $i < $this->words; $i++) {
            $lw .= (isset($w[$i])) ? $w[$i] : '';
            if ($i!=($this->words-1)&&(isset($w[$i]))) $lw .= ' ';
        }
        $rd['lastword'] = strrev(trim($lw));
        unset($w, $lw);
        return $rd;
    }

    /**
    * Taken from sunshine, thanks! :D
    * Most img tags don't have an absolute URL, but for the purposes of downloading & storing, we need one.
    * This function constructs an absolute URL from a whatever is specified as the "src" attribute of an image tage
    * 
    * @param String $img : the src attribute of a <img> tag
    * @param String $lint_domain : A URL parsed with PHP's parse_url function
    * @return string : The absolute URL of the $img parameter.
    */
  
    public static function get_image_absolute_url($img,$lint_domain) {
        // if the URL of the image src attr is relative to the page path (instead of an absolute url that starts with http:// or https://)
        // IMPORTANT:  Youtube likes to start img src attribute with // instead of http:// for some reason.  Way to use the same standards as everyone else, guys.
        $is_abs_path = false;
        if (strpos($img,"//")  !== 0 && strpos($img,"http")  !== 0) {
            // the url we're building needs to start with http://www.domain.com/
            $imgix = $lint_domain['scheme'] . "://" . $lint_domain['host'];
            // if the url is not relative to the document root on the server, it will be relative to the path of the page we're on.  
            // figure out where the image lives, and build the url path starting with /
            if (strpos($img,"/")  !== 0) {
                $imgix = $imgix . $lint_domain['path'];
            } else $is_abs_path = true; // this is an absolute path already, we don't need the extra trailing slash.
  
            // add the training slash if needed.
            if (substr($imgix,(strlen($imgix)-1),1) != "/" && !$is_abs_path) $imgix .= "/";
            $img = $imgix . $img;
        }
  
        else if (strpos($img,"//") === 0) { $img = $lint_domain['scheme'] . ":" . $img; }
  
        return $img;
   
    }

    public function getInnerHTML($domobj, $colours) {
        // set colours
        if (is_array($colours)) {
            $this->bgcolour = isset($colours['bgcolour']) ? $colours['bgcolour'] : $this->bgcolour;
            $this->colour = isset($colours['colour']) ? $colours['colour'] : $this->colour;
        }
        // reset
        $this->innerHTML = '';
        $this->currentTag = '';
        $this->myCount = 0;
        // we set this each time to use user selected colours (not yet implemented)
        $this->highlightstart = '<span style="display:inline;background-color: ' . $this->bgcolour . '; color: ' . $this->colour . ';">';
        $this->highlightend = '</span>';
        $this->iterateDom($domobj);
        echo "\n\n" . $this->innerHTML;
        die;
        return $this->innerHTML;
    }

    private function iterateDom($domobj) {

        /*
            @TODO
            bugs: was duplicating content when selection was in the same tag-type and on the same level (ie: like this: "here</p><p>and here")

        */

        $str = '';
        //print_r($domobj);
        if ($this->myCount==0) {
            $this->masterCount = count($domobj);
        }
        echo "\ntc: " . $this->myCount;
        echo "\nc: " . count($domobj); 
        echo "\nmc: " . $this->masterCount;
        if (count($domobj)==0 && isset($domobj[0])) {
            $str .= $this->highlightstart . $domobj[0] . $this->highlightend;
            if ($this->myCount < $this->masterCount) {
                $str .= '</'.$this->currentTag.'><'.$this->currentTag.'>';
            }
        } 
        
        foreach($domobj as $tag => $content) {
            $this->currentTag = $tag;
            if (is_object($content)) {
                $this->iterateDom($content);
            } else {
                if ($this->myCount == 0) {
                    $str .= $this->highlightstart . $content . $this->highlightend . '</'.$tag.'>';
                } elseif ($content=='') {
                    $str .= '</'.$tag.'>';
                } elseif ($this->myCount == count($domobj)) {
                    $str .= '<'.$tag.'>' . $this->highlightstart . $content . $this->highlightend;
                } elseif ($content!='') {
                    $str .= '<'.$tag.'>' . $this->highlightstart . $content . $this->highlightend . '</'.$tag.'>';
                }
            }
            $this->myCount++;
        }

        $this->innerHTML = $this->innerHTML . $str;
    }

}

?>
