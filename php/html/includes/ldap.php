<?php
function ldap_authenticate($username, $password) {
    $ldap_host = getenv("LDAP_HOST");
    $ldap_dn = getenv("LDAP_BASE_DN");
    $ou_list = ['Drivers', 'Insiders', 'Managers'];

    foreach ($ou_list as $ou) {
        $ldap_user = "uid=$username,ou=$ou,$ldap_dn";
        $ds = ldap_connect($ldap_host);
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

        if (@ldap_bind($ds, $ldap_user, $password)) {
            $role = match ($ou) {
                'Managers' => 'manager',
                default => 'employee'
            };
            return [
                "status" => true,
                "role" => $role
            ];
        }
    }

    return ["status" => false];
}
?>
