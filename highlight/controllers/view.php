<?

class View extends CI_Controller {

    function __construct() {
        parent::__construct();
    }

    function index() {
        $id = $this->input->get('id');
    
        $this->load->model('Highlights');

        $viewdata = $this->Highlights->getViewdata($id);
        
        $url = $viewdata['url'];

        // is this enough to avoid URL exploitation?
        if (substr(strtolower($url), 0, 4) != "http") {
            echo "Invalid URL entered. Please add protocol scheme.";
            $this->load->view('add');
            die; // @TODO redirect or load index?
        }

        $url2 = urlencode($url); //?!

        // @TODO assess need to use strip_tags or something to preg_replace <script> contents ?
        $webdata = (strstr(' ', $url)) ? file_get_contents($url2) : file_get_contents($url);

        // Now we insert markup to highlight the text
        // @TODO implement user-selected highlight (and background?) colour

        $bgcolour = '#FFB3F9';
        $colour = '#000000';

/*
Problem:
DB stores plain text without markup, we read in the full markup
We need to find where the plaintext occurs, and markup the markup
properly, while maintaining markup sanity
*/

        $webdata = preg_replace('/\n/', '', $webdata);
        $webdata = preg_replace('/\r\n/', '', $webdata);

        $fpos = @stripos(strtolower($webdata), strtolower($viewdata['firstword']));
        $tl = strlen($viewdata['rawtext']);
        $diff = strlen($viewdata['rawtext']) - strlen($viewdata['text']);
        $epos = $fpos + $tl;
        $treatment = '<document>' . $viewdata['rawdomtext'] . '</document>'; // substr($webdata, $fpos, $tl - $diff);
        echo "fpos: " . $fpos . "<br />tl: " . $tl . '<br />diff: ' . $diff . '<br />' . $epos;
        $highlightstart = '<span style="display:inline;background-color: ' . $bgcolour . '; color: ' . $colour . ';">';
        $highlightend = '</span>';

        if (!$fpos || !$tl) {
            $content = $webdata; // no highlighting;
        } else {
            $content  = substr($webdata, 0, $fpos);
            
            $t = simplexml_load_string($treatment);
            $inner = $this->Highlights->getInnerHTML($t, array('bgcolour'=>$bgcolour, 'colour'=>$colour));
            
            $content .= $inner;

            $content .= substr($webdata, $epos);
            
        }

        $viewdata['content'] = $content;
        unset($content, $tl, $bgcolour, $colour, $pos, $inner, $t);

        //$urldata = strip_tags($urldata);

        $this->load->view('global/header');

        $this->load->view('highlightjs', $viewdata);
        $this->load->view('highlight', $viewdata);

        $this->load->view('global/footer');
    }
}

?>
