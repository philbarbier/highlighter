<?=$this->load->view('global/header')?>
<?=@validation_errors() ?>
<?=form_open('/highlight/app/index')?>
<br/>
<?=form_label("URL to highlight from:")?>
<br/>
<?=form_input('url')?>
<br />
<br />
<?=form_submit('Submit', 'Submit')?>
<?=form_close()?>
<?=$this->load->view('global/footer')?>
