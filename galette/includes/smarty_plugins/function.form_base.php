<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {form_base} function plugin
 *
 * Type:     function<br>
 * Name:     form_base<br>
 * Date:     Mar 22, 2006<br>
 * Purpose:  make an form with auto validation<br>
 * Input:<br>
 *         - form_name = identifier
 *
 *
 * Examples:
 * <pre>
 * {form_base form_name="nameOfForm"}
 * </pre>
 * @author   Ludovic BELLIÈRE <xrogaan at gmail dot com>
 * @version  1.0
 * @param array
 * @param Smarty
 * @return string
 */

function smarty_function_form_base ($params, &$smarty) {
    if (empty($params['form_name']))
        $smarty->trigger_error("form_base: missing 'form_name' parameter");
    if (!isset($smarty->_tpl_vars['formBase_'.$params['form_name']]))
        $smarty->trigger_error("form_base: missing 'formBase_{$params['form_name']}' form");

    ${'formBase_'.$params['form_name']} = $smarty->_tpl_vars['formBase_'.$params['form_name']];
    unset($smarty->_tpl_vars['formBase_'.$params['form_name']]);

    $form = ${'formBase_'.$params['form_name']}['formtag'];
    $started = false;

    foreach(${'formBase_'.$params['form_name']}['item'] as $v ) {
        if (isset($v['chapter'])) {
            if ($started) {
                $form.= '</div>';
                $form.= '</fieldset>';
            }
            $form.= '<fieldset class="cssform">';
            $form.= '<legend class="ui-state-active ui-corner-top">' . $v['chapter'] . '</legend>';
            $form.= '<div>';
            $started = true;
          } else {
            $form.= '<p>';
            $form.= '<label for="'.$v['form_key'].'" class="bline">'.$v['name'].'</label>';
            $form.= $v['input'];

            $etxt = '';
            if (!empty($v['help'])) {
                $etxt = $v['help'];
            }
            if (!empty($v['error'])) {
                $etxt.= "\n".$v['error'];
            }
            if (!empty($v['help']) || !empty($v['error'])) {
                $extra = '<span class="example"> %s </span>';
                $form.= sprintf($extra, $etxt);
            }
            $form.= "</p>";
        }
    }
    $form.= '</div></fieldset>';
    $form.= '<div class="button-container">';
    $form.= '<input type="submit" value="Valider" />';
    $form.= '</div></form>';
    return $form;
}

?>
