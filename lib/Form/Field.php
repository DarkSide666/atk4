<?php
/**
 * Implementation of abstract form's field
 *
 * @author		Romans <romans@adevel.com>
 * @copyright	See file COPYING
 * @version		$Id$
 */
abstract class Form_Field extends AbstractView {
    /**
     * Description of the field shown next to it on the form
     */
    public $error_template;    // template used to put errors on the field line
    public $caption;
    protected $value=null;        // use $this->get(), ->set().
    public $short_name=null;
    public $attr=array();
    public $no_save=null;

    public $field_prepend='';
    public $field_append='';

    protected $onchange=null;
    protected $onkeypress=null;
    protected $onfocus=null;
    protected $onblur=null;
    protected $onclick=null;
    public $comment='&nbsp;';
    protected $disabled=false;

    // Field customization
    private $separator=':';
    function setCaption($_caption){
        $this->caption=$_caption;
        return $this;
    }
    function displayFieldError($msg=null){
        if(!isset($msg))$msg='Error in field "'.$this->caption.'"';
        if($this->api->isAjaxOutput()){
            echo $this->add('Ajax')->displayAlert($msg)->execute();
        }
        $this->owner->errors[$this->short_name]=$msg;
    }
    function setNoSave(){
        // Field value will not be saved into defined source (such as database)
        $this->no_save=true;
        return $this;
    }
    function disable(){
    	// sets 'disabled' property and setNoSave()
    	$this->setProperty('disabled',true);
    	$this->setNoSave();
    	$this->disabled=true;
    	return $this;
    }
    function isDisabled(){
    	return $this->disabled;
    }
    function set($value){
        // Use this function when you want to assign $this->value. If you use this function, your field will
        // operate in AJAX mode
        $this->value=$value;
        return $this;
    }
    function get(){
        return $this->value;
    }
    function setProperty($property,$value){
        $this->attr[$property]=$value;
    }
	function onChange(){
		return $this->onchange=$this->add('Ajax');
	}
	function onKeyPress(){
		return $this->onkeypress=$this->add('Ajax');
	}
	function onFocus(){
		return $this->onfocus=$this->add('Ajax');
	}
	function onBlur(){
		return $this->onblur=$this->add('Ajax');
	}
	function onClick(){
		return $this->onclick=$this->add('Ajax');
	}

    function clearFieldValue(){
        $this->value=null;
    }
    function loadPOST(){
        $gpc = get_magic_quotes_gpc();
        if ($gpc){
            if(isset($_POST[$this->name]))$this->set(stripslashes($_POST[$this->name]));
        } else {
            if(isset($_POST[$this->name]))$this->set($_POST[$this->name]);
        }
    }
    function validate(){
        // we define "validate" hook, so actual validators could hook it here
        // and perform their checks
        if(is_bool($result = $this->hook('validate')))return $result;
    }
    function getInput($attr=array()){
        // This function returns HTML tag for the input field. Derived classes should inherit this and add
        // new properties if needed
        return $this->getTag('input',
        	array_merge(array(
				'name'=>$this->name,
				'id'=>$this->name,
				'value'=>$this->value,
				'onchange'=>(is_null($this->onchange)?'':$this->onchange->getString()),
				'onKeyPress'=>(is_null($this->onkeypress)?'denyEnter(event)':$this->onkeypress->getString()),
				'onfocus'=>(is_null($this->onfocus)?'':$this->onfocus->getString()),
				'onblur'=>(is_null($this->onblur)?'':$this->onblur->getString()),
				'onclick'=>(is_null($this->onclick)?'':$this->onclick->getString()),
			),$attr,$this->attr)
		);
    }
    function setSeparator($separator){
        $this->separator = $separator;
        return $this;
    }
    function render(){
        if(!$this->error_template)$this->error_template = $this->owner->template_chunks['field_error'];
        $this->template->trySet('field_caption',$this->caption?($this->caption.$this->separator):'');
        $this->template->trySet('field_name',$this->name);
        $this->template->trySet('field_comment',$this->comment);
        $this->template->set('field_input',$this->field_prepend.$this->getInput().$this->field_append);
        $this->template->trySet('field_error',
                             isset($this->owner->errors[$this->short_name])?
                             $this->error_template->set('field_error_str',$this->owner->errors[$this->short_name])->render()
                             :''
                             );
        $this->output($this->template->render());
    }

    function getTag($tag, $attr=null, $value=null){
        /**
         * Draw HTML attribute with supplied attributes.
         *
         * Short description how this getTag may be used:
         *
         * Use get tag to build HTML tag.
         * echo getTag('img',array('src'=>'foo.gif','border'=>0);
         *
         * The unobvius advantage of this function is ability to merge
         * attribute arrays. For example, if you have function, which
         * must display img tag, you may add optional $attr argument
         * to this function.
         *
         * function drawImage($src,$attr=array()){
         *     echo getTag('img',array_merge(array('src'=>$src),$attr));
         * }
         *
         * so calling drawImage('foo.gif') will echo: <img src="foo.gif">
         *
         * The benefit from such a function shows up when you use 2nd argument:
         *
         * 1. adding additional attributes
         * drawImage('foo.gif',array('border'=>0'));
         * --> <img src="foo.gif" border="0">
         * (NOTE: you can even have attr templates!)
         *
         * 2. adding no-value attributes, such as nowrap:
         * getTag('td',arary('nowrap'=>true));
         * --> <td nowrap>
         *
         * 3. disabling some attributes.
         * drawImage('foo.gif',array('src'=>false));
         * --> <img>
         *
         * 4. re-defining attributes
         * drawImage('foo.gif',array('src'=>'123'));
         * --> <img src="123">
         *
         * 5. or you even can re-define tag itself
         * drawImage('foo.gif',array(
         *                      ''=>'input',
         *                      'type'=>'picture'));
         * --> <input type="picture" src="foo.gif">
         *
         * 6. xml-valid tags without closing tag
         * getTag('img/',array('src'=>'foo.gif'));
         * --> <img src=>"foo.gif"/>
         *
         * 7. closing tags
         * getTag('/td');
         * --> </td>
         *
         * 8. using $value will add $value after tag followed by closing tag
         * getTag('a',array('href'=>'foo.html'),'click here');
         * --> <a href="foo.html">click here</a>
         *
         * 9. you may not skip attribute argument.
         * getTag('b','text in bold');
         * --> <b>text in bold</b>
         *
         * 10. nesting
         * getTag('a',array('href'=>'foo.html'),getTag('b','click here'));
         * --> <a href="foo.html"><b>click here</b></a>
         */
         
        if(is_string($attr)){
            $value=$attr;
            $attr=null;
        }
        if(!$attr){
            return "<$tag>".($value?$value."</$tag>":"");
        }
        $tmp = array();
        if(substr($tag,-1,1)=='/'){
            $tag = substr($tag,0,-1);
            $postfix = '/';
        } else $postfix = '';
        foreach ($attr as $key => $val) {
            if($val === false) continue;
            if($val === true) $tmp[] = "$key";
            elseif($key === '')$tag=$val;
            else $tmp[] = "$key=\"".htmlspecialchars($val)."\"";
        }
        return "<$tag ".join(' ',$tmp).$postfix.">".($value?$value."</$tag>":"");
    }
}


class Form_Field_Line extends Form_Field {
    function getInput($attr=array()){
        return parent::getInput(array_merge(array('type'=>'text'),$attr));
    }
}
class Form_Field_URL extends Form_Field_Line {
}
class Form_Field_Search extends Form_Field {
    // WARNING: <input type=search> is safari extention and is will not validate as valid HTML
    function getInput($attr=array()){
        return parent::getInput(array_merge(array('type'=>'search'),$attr));
    }
}
class Form_Field_Checkbox extends Form_Field {
    function getInput($attr=array()){
        $this->template->trySet('field_caption','');
        return parent::getInput(array_merge(
                    array(
                        'type'=>'checkbox',
                        'value'=>'Y',
                        'checked'=>$this->value=='Y'
                        ),$attr
                    )).' - '.$this->caption;
    }
    function loadPOST(){
        if(isset($_POST[$this->name])){
            $this->set('Y');
        }else{
            $this->set('');
        }
    }
}
class Form_Field_Password extends Form_Field {
    function getInput($attr=array()){
        return parent::getInput(array_merge(
                    array(
                        'type'=>'password',
                        ),$attr
                    ));
    }
}
class Form_Field_Hidden extends Form_Field {
    function getInput($attr=array()){
        return parent::getInput(array_merge(
                    array(
                        'type'=>'hidden',
                        ),$attr
                    ));
    }
    function render(){
		$this->template = $this->owner->template_chunks['hidden_form_line'];
		$this->template->set('hidden_field_input',$this->getInput());
		$this->owner->template_chunks['form']->append('form_body',$this->template->render());
    }

}
class Form_Field_Readonly extends Form_Field {
    function init(){
    	parent::init();
    	$this->setNoSave();
    }
    
    function getInput($attr=array()){
        if (isset($this->value_list)){
            return $this->value_list[$this->value];
        } else {
            return $this->value;
        }
    }
    function setValueList($list){
        $this->value_list = $list;
    }
    
}
class Form_Field_File extends Form_Field {
	function init(){
		$this->attr['type'] = 'file';
		//we have to set enctype for file upload
		$this->owner->template->set('enctype', "enctype=\"multipart/form-data\"");
	}
}
class Form_Field_Time extends Form_Field {
	function getInput($attr=array()){
        return parent::getInput(array_merge(array('type'=>'text', 
			'value'=>format_time($this->value)),$attr));
	}
}
class Form_Field_Date extends Form_Field {
	private $sep = '-';
	private $is_valid = false;
	
	/*function getInput($attr=array()){
        return parent::getInput(array_merge(array('type'=>'text', 
			'value'=>($this->is_valid ? date('Y-m-d', $this->value) : $this->value)),$attr));
	}*/
	private function invalid(){
        return $this->displayFieldError('Not a valid date');
	}
	function validate(){
		//empty value is ok
		if($this->value==''){
			$this->is_valid=true;
			return parent::validate();
		}
		//checking if there are 2 separators
		if(substr_count($this->value, $this->sep) != 2){
			$this->invalid();
		}else{
			$c = split($this->sep, $this->value);
			//day must go first, month should be second and a year should be last
			if(strlen($c[0]) != 4 || 
				$c[1] <= 0 || $c[1] > 12 || 
				$c[2] <= 0 || $c[2] > 31)
			{
				$this->invalid();
			}
			//now attemting to convert to date
			if(strtotime($this->value)==''){
				$this->invalid();
			}else{
				//$this->set(strtotime($this->value));
				$this->set($this->value);
				$this->is_valid=true;
			}
		}
		return parent::validate();
	}
}
class Form_Field_Text extends Form_Field {
    function init(){
        $this->attr=array('cols'=>'30','rows'=>5);
        parent::init();
    }
    function getInput($attr=array()){
        return 
            parent::getInput(array_merge(array(''=>'textarea'),$attr)).
            htmlspecialchars(stripslashes($this->value)).
            $this->getTag('/textarea');
    }
}
class Form_Field_Money extends Form_Field_Line {
    function getInput($attr=array()){
        return parent::getInput(array_merge(array('value'=>number_format($this->value,2)),$attr));
    }
}

// The following clases operates with predefined value lists. 
// User is picking one or several options
class Form_Field_ValueList extends Form_Field {
    /*
     * This is abstract class. Use this as a base for all the controls
     * which operate with predefined values such as dropdowns, checklists
     * etc
     */
    public $value_list=array(
            0=>'No available options #1',
            1=>'No available options #2',
            2=>'No available options #3'
            );
    function getValueList(){
        return $this->value_list;
    }
    function setValueList($list){
        $this->value_list = $list;
    }
    function loadPOST(){
        $data=$_POST[$this->name];
        if(is_array($data))$data=join(',',$data);
        $gpc = get_magic_quotes_gpc();
        if ($gpc){
            if(isset($_POST[$this->name]))$this->set(stripslashes($data));
        } else {
            if(isset($_POST[$this->name]))$this->set($data);
        } 
    }
}
class Form_Field_Dropdown extends Form_Field_ValueList {
    function validate(){
        if(!isset($this->value_list[$this->value])){
        	if($this->api->isAjaxOutput()){
		        $this->add('Ajax')->displayAlert($this->short_name.": This is not one of the offered values")
		        	->execute();
		    }
            $this->owner->errors[$this->short_name]="This is not one of the offered values";
        }
        return parent::validate();
    }
    function getInput($attr=array()){
        $output=$this->getTag('select',array_merge(array(
                        'name'=>$this->name,
                        'id'=>$this->name,
						'onchange'=>
						($this->onchange)?$this->onchange->getString():''
                        ),
                    $attr,
                    $this->attr)
                );
        foreach($this->getValueList() as $value=>$descr){
            $output.=
                $this->getTag('option',array(
                        'value'=>$value,
                        'selected'=>$value == $this->value
                    ))
                .htmlspecialchars($descr)
                .$this->getTag('/option');
        }
        $output.=$this->getTag('/select');
        return $output;
    }
}
class Form_Field_CheckboxList extends Form_Field_ValueList {
    /*
     * This field will create 2 column of checkbox+labels from defived value
     * list. You may check as many as you like and when you save their ID
     * values will be stored coma-separated in varchar type field.
     *
		$f->addField('CheckboxList','producers','Producers')->setValueList(array(
                    1=>'Mr John',
                    2=>'Piter ',
                    3=>'Michail Gershwin',
                    4=>'Bread and butter',
                    5=>'Alex',
                    6=>'Benjamin',
                    7=>'Rhino',
                    ));

                    */

    var $columns=2;
    function validate(){
        return true;
    }
    function getInput($attr=array()){
        $output='<table border=0>';
        $column=0;
        $current_values=explode(',',$this->value);
        foreach($this->getValueList() as $value=>$descr){
            if($column==0){
                $output.="<tr><td>";
            }else{
                $output.="</td><td>";
            }

            $output.=
                $this->getTag('input',array(
                            'type'=>'checkbox',
                            'value'=>$value,
                            'name'=>$this->name.'[]',
                            'checked'=>in_array($value,$current_values)
                            )).htmlspecialchars($descr);
            $column++;
            if($column==$this->columns){
                $output.="</td></tr>";
                $column=0;
            }
        }
        $output.="</table>";
        return $output;
    }
    
    function loadPOST(){
        $data=$_POST[$this->name];
        if(is_array($data))
           $data=join(',',$data);
        else 
           $data='';

        $gpc = get_magic_quotes_gpc();
        if ($gpc){
            $this->set(stripslashes($data));
        } else {
            $this->set($data);
        } 
    }    
}

class Form_Field_Radio extends Form_Field_ValueList {
    function validate(){
        if(!isset($this->value_list[$this->value])){
        	if($this->api->isAjaxOutput()){
		        echo $this->add('Ajax')->displayAlert($this->short_name.":"."This is not one of offered values")->execute();
		    }
            $this->owner->errors[$this->short_name]="This is not one of offered values";
        }
        return parent::validate();
    }
    function getInput($attr=array()){
        $output = "";
        foreach($this->getValueList() as $value=>$descr){
            $output.=
                $this->getTag('input',
                array_merge(
                    array(
                        'name'=>$this->name,
                        'type'=>'radio',
                        'value'=>$value,
                        'checked'=>$value == $this->value
                    ),
                    $this->attr,
                    $attr
                ))
                .$this->getTag('/input')
                .htmlspecialchars($descr) . "<br />";
        }
        return $output;
    }
}

