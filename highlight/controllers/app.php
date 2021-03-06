<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class App extends CI_Controller {

    public function __construct() {
        parent::__construct();
        /*
        $this->load->library('url_debugger');
        $this->load->library('highlighter');
        */
    }

	public function index() {

        $url = $this->input->post('url');

        // is this enough to avoid URL exploitation?
        /* NOPE 
        if (substr(strtolower($url), 0, 4) != "http") {
            echo "Invalid URL entered. Please add protocol scheme.";
            $this->load->view('add');
            die; // @TODO redirect or load index
        }
         */

        // But this is
        $this->form_validation->set_rules('url', 'URL', 'required|callback__valid_url|__callback_url_exist');
        if ($this->form_validation->run() == FALSE) {
            $this->load->view('add');
        } else {
            $url2 = urlencode($url); //?!
            // @TODO assess need to use strip_tags or something to preg_replace <script> contents ?
            #$urldata = (strstr(' ', $url)) ? file_get_contents($url2) : file_get_contents($url);
            // Curl lib does this better :D
            $this->curl->option(CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:11.0) Gecko/20100101 Firefox/11.0");
            $urldata = $this->curl->simple_get($url);

            //$urldata = strip_tags($urldata);
            $data = array();
            $data['url'] = $url;
            $data['urldata'] = $urldata;
            $this->load->view('global/header');
            $this->load->view('suckerassets');
            $this->load->view('suckermenu');
            $this->load->view('suckpage', $data);
            $this->load->view('global/footer');
        }        
    }
	
	public function highlight() {
        $url = $this->input->post('url');
        $text = $this->input->post('text');
		$params = array(
			'url' => $url,
			'text' => $text
		);
        $data = null;
		$this->load->library('highlighter', $data);
	}

    /*
        public function suckpage()
        pulls user-inputted page in and renders content
        to enable user to select text to highlight on UI

    */

    private function strip_only($str, $tags) {
        if(!is_array($tags)) {
            $tags = (strpos($str, '>') !== false ? explode('>', str_replace('<', '', $tags)) : array($tags));
            if(end($tags) == '') array_pop($tags);
        }
        foreach($tags as $tag) 
            $str = preg_replace('#</?'.$tag.'[^>]*>#is', '', $str);
        return $str;
    }

    // Validation Callback
    function _valid_url($str){
        $pattern = "|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i";
        if (!preg_match($pattern, $str)){
            $this->form_validation->set_message('_valid_url', 'The URL you entered is not correctly formatted.');
            return FALSE;
        }
        return TRUE;
    }       

    // Validation Callback
    function _url_exists($url){                                   
        $url_data = parse_url($url); // scheme, host, port, path, query
        if(!fsockopen($url_data['host'], isset($url_data['port']) ? $url_data['port'] : 80)){
            $this->form_validation->set_message('_url_exists', 'The URL you entered is not accessible.');
            return FALSE;
        }               
        return TRUE;
    }  

    function savehighlight() {
        $this->load->model('Highlights');

        $newid = $this->Highlights->returnID();
        $response = array();

        // Can be a relative or absolute URL
        // This must have a trailing slash
        $response['urlstub'] = '/highlight/view/'; 

        if (!$newid) {
            $response['id'] = false;
        } else {
            $savedata = array();
            $savedata['url'] = $this->input->post('url');
            $selectedtext = $this->input->post('text');
            $savedata['rawdomtext'] = $selectedtext;
            $savedata['hid'] = $newid;
            $res = $this->Highlights->save($savedata);
            $response['id'] = ($res!=false) ? $newid : false;
        }
        echo json_encode($response);
        die;

    }

}
