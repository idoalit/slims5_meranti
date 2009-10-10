<?php
/**
 *
 * Member Area/Information
 * Copyright (C) 2009  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

// required file
require LIB_DIR.'member_logon.inc.php';
// check if member already logged in
$is_member_login = utility::isMemberLogin();

// if member is logged out
if (isset($_GET['logout']) AND $_GET['logout'] == '1') {
    // write log
    utility::writeLogs($dbs, 'member', $_SESSION['email'], 'Login', $_SESSION['member_name'].' Log Out from address '.$_SERVER['REMOTE_ADDR']);
    // completely destroy session cookie
    simbio_security::destroySessionCookie(null, SENAYAN_MEMBER_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR, false);
    header('Location: index.php?p=member');
    exit();
}

// if there is member login action
if (isset($_POST['logMeIn']) AND !$is_member_login) {
    $username = trim(strip_tags($_POST['memberUserName']));
    $password = trim(strip_tags($_POST['memberPassWord']));
    // check if username or password is empty
    if (!$username OR !$password) {
        echo '<div class="errorBox">'.__('Please fill your Username and Password to Login!').'</div>';
    } else {
        // regenerate session ID to prevent session hijacking
        session_regenerate_id(true);
        // create logon class instance
        $logon = new member_logon($username, $password);
        if ($logon->valid($dbs)) {
            // write log
            utility::writeLogs($dbs, 'member', $username, 'Login', 'Login success for member '.$username.' from address '.$_SERVER['REMOTE_ADDR']);
            header('Location: index.php?p=member');
            exit();
        } else {
            // write log
            utility::writeLogs($dbs, 'member', $username, 'Login', 'Login FAILED for member '.$username.' from address '.$_SERVER['REMOTE_ADDR']);
            // message
            $msg = '<div class="errorBox">'.__('Login FAILED! Wrong username or password!').'</div>';
            simbio_security::destroySessionCookie($msg, SENAYAN_MEMBER_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR, false);
        }
    }
}

// check if member already login
if (!$is_member_login) {
?>
    <fieldset id="memberLogin">
    <legend><?php echo __('Library Member Login'); ?></legend>
    <div class="loginInfo"><?php echo __('Please insert your E-mail address and password
        given by library system administrator. If you are library\'s member and don\'t have a password yet,
        please contact library staff.'); ?></div>
    <form action="index.php?p=member" method="post">
    <div class="fieldLabel"><?php echo __('Member E-Mail'); ?></div>
        <div><input type="text" name="memberUserName" /></div>
    <div class="fieldLabel marginTop"><?php echo __('Password'); ?></div>
        <div><input type="password" name="memberPassWord" /></div>
    <div class="marginTop"><input type="submit" name="logMeIn" value="Logon" />
    </div>
    </form>
    </fieldset>
<?php
} else {
    /*
     * Function to show membership detail of logged in member
     *
     * @return      string
     */
    function showMemberDetail()
    {
        // show the member information
        $_detail = '<table class="memberDetail" cellpadding="5" cellspacing="0">'."\n";
        // member notes and pending information
        if ($_SESSION['m_membership_pending'] || $_SESSION['m_is_expired']) {
            $_detail .= '<tr>'."\n";
            $_detail .= '<td class="alterCell" width="15%"><strong>Notes</strong></td><td class="alterCell2" colspan="3">';
            if ($_SESSION['m_is_expired']) {
                $_detail .= '<div style="color: #f00;">'.__('Your Membership Already EXPIRED! Please extend your membership.').'</div>';
            }
            if ($_SESSION['m_membership_pending']) {
                $_detail .= '<div style="color: #f00;">'.__('Membership currently in pending state, no loan transaction can be made yet.').'</div>';
            }
            $_detail .= '</td>';
            $_detail .= '</tr>'."\n";
        }
        $_detail .= '<tr>'."\n";
        $_detail .= '<td class="alterCell" width="15%"><strong>'.__('Member Name').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['m_name'].'</td>';
        $_detail .= '<td class="alterCell" width="15%"><strong>'.__('Member ID').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['mid'].'</td>';
        $_detail .= '</tr>'."\n";
        $_detail .= '<tr>'."\n";
        $_detail .= '<td class="alterCell" width="15%"><strong>'.__('Member Email').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['m_email'].'</td>';
        $_detail .= '<td class="alterCell" width="15%"><strong>'.__('Member Type').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['m_member_type'].'</td>';
        $_detail .= '</tr>'."\n";
        $_detail .= '<tr>'."\n";
        $_detail .= '<td class="alterCell" width="15%"><strong>'.__('Register Date').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['m_register_date'].'</td>';
        $_detail .= '<td class="alterCell" width="15%"><strong>'.__('Expiry Date').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['m_expire_date'].'</td>';
        $_detail .= '</tr>'."\n";
        $_detail .= '</table>'."\n";


        return $_detail;
    }


    /* callback function to show overdue */
    function showOverdue($obj_db, $array_data)
    {
        $_curr_date = date('Y-m-d');
        if (simbio_date::compareDates($array_data[3], $_curr_date) == $_curr_date) {
            return '<strong style="color: #f00;">'.$array_data[3].' '.__('OVERDUED').'</strong>';
        } else {
            return $array_data[3];
        }
    }


    /*
     * Function to show list of logged in member loan
     *
     * @param       int         number of loan records to show
     * @return      string
     */
    function showLoanList($num_recs_show = 20)
    {
        global $dbs;
        require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
        require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
        require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
        require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';

        // table spec
        $_table_spec = 'loan AS l
        LEFT JOIN member AS m ON l.member_id=m.member_id
        LEFT JOIN item AS i ON l.item_code=i.item_code
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id';

        // create datagrid
        $_loan_list = new simbio_datagrid();
        $_loan_list->setSQLColumn('l.item_code AS \''.__('Item Code').'\'',
            'b.title AS \''.__('Title').'\'',
            'l.loan_date AS \''.__('Loan Date').'\'',
            'l.due_date AS \''.__('Due Date').'\'');
        $_loan_list->setSQLorder('l.loan_date DESC');
        $_criteria = 'm.member_id=\''.$_SESSION['mid'].'\' ';
        $_loan_list->setSQLCriteria($_criteria);

        // modify column value
        $_loan_list->modifyColumnContent(3, 'callback{showOverdue}');
        // set table and table header attributes
        $_loan_list->table_attr = 'align="center" class="memberLoanList" cellpadding="5" cellspacing="0"';
        $_loan_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
        $_loan_list->using_AJAX = false;
        // return the result
        $_result = $_loan_list->createDataGrid($dbs, $_table_spec, $num_recs_show);
        $_result = '<div class="memberLoanListInfo">'.$_loan_list->num_rows.' '.__('item(s) currently on loan').'</div>'."\n".$_result;
        return $_result;
    }

    // show all
    echo '<h3 class="memberInfoHead">'.__('Member Detail').'</h3>'."\n";
    echo showMemberDetail();
    echo '<h3 class="memberInfoHead">'.__('Your Current Loan').'</h3>'."\n";
    echo showLoanList();
}
?>
