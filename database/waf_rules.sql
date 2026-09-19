-- APX WAF 内置规则集（观察模式默认全部启用）
-- pattern 为 PCRE 正则（不含分隔符），加载时统一用 ~...~iu 包裹
SET NAMES utf8mb4;

INSERT INTO `waf_rules` (`name`, `category`, `pattern`, `score`, `enabled`) VALUES
('sqli.union_select', 'sqli', '\\bunion\\b\\s+(all\\s+)?\\bselect\\b', 8, 1),
('sqli.sleep_bench', 'sqli', '\\b(sleep|benchmark)\\s*\\(', 8, 1),
('sqli.info_schema', 'sqli', '\\b(information_schema|performance_schema)\\b', 8, 1),
('sqli.boolean_tautology', 'sqli', '(\\bor\\b|\\band\\b)\\s+\\d+\\s*=\\s*\\d+', 8, 1),
('sqli.stacked', 'sqli', ';\\s*(drop|truncate|alter)\\s+table', 8, 1),
('sqli.comment_terminate', 'sqli', '(--\\s|#\\s|/\\*|/\\*)\\s*(and|or|select|union)', 6, 1),
('sqli.into_outfile', 'sqli', '\\binto\\s+(out|dump)file\\b', 8, 1),
('sqli.extractvalue', 'sqli', '\\b(extractvalue|updatexml|name_const)\\s*\\(', 8, 1),
('sqli.hex_encode', 'sqli', '\\b0x[0-9a-f]{8,}\\b', 4, 1),

('xss.script_tag', 'xss', '<\\s*script[^>]*>', 10, 1),
('xss.event_handler', 'xss', '\\bon(error|load|click|mouseover|focus|blur|submit)\\s*=', 10, 1),
('xss.javascript_proto', 'xss', 'javascript\\s*:', 10, 1),
('xss.vbscript_proto', 'xss', 'vbscript\\s*:', 10, 1),
('xss.data_uri_html', 'xss', 'data\\s*:\\s*text/html', 10, 1),
('xss.svg_onload', 'xss', '<\\s*svg[^>]*\\bonload', 10, 1),
('xss.iframe_src', 'xss', '<\\s*iframe[^>]*src\\s*=', 8, 1),
('xss.eval_call', 'xss', '\\b(eval|atob|Function)\\s*\\(', 8, 1),
('xss.document_cookie', 'xss', '\\bdocument\\s*\\.\\s*cookie\\b', 8, 1),
('xss.innerhtml', 'xss', '\\binnerHTML\\s*=', 8, 1),

('traversal.dotdot', 'traversal', '(\\.\\./|\\.\\\\|%2e%2e%2f|%252e%252e)', 10, 1),
('traversal.etc_passwd', 'traversal', '(etc/|etc\\\\)(passwd|shadow|hosts)', 10, 1),
('traversal.win_ini', 'traversal', '(boot\\.ini|win\\.ini|system32)', 10, 1),
('traversal.proc_self', 'traversal', '/proc/self/(environ|cmdline)', 10, 1),
('traversal.php_filter', 'traversal', 'php://(filter|input|data)', 10, 1),

('lfi.include_remote', 'lfi', '\\b(include|require)(_once)?\\s*\\(\\s*[\'"]?(https?|ftp|php)://', 10, 1),
('lfi.wrapper', 'lfi', '\\b(file|zip|phar|glob|expect)://', 10, 1),
('lfi.log_poison', 'lfi', '(access\\.log|error\\.log|/var/log/)', 8, 1),

('cmdi.shell_pipe', 'cmdi', ';\\s*(cat|ls|whoami|id|pwd|uname|ifconfig|netstat)\\b', 12, 1),
('cmdi.backtick', 'cmdi', '`', 6, 1),
('cmdi.dollar_paren', 'cmdi', '\\$\\(\\s*\\w+', 12, 1),
('cmdi.system_call', 'cmdi', '\\b(system|passthru|shell_exec|popen|proc_open|pcntl_exec)\\s*\\(', 12, 1),
('cmdi.wget_curl', 'cmdi', '\\b(wget|curl)\\s+', 12, 1),
('cmdi.rm_f', 'cmdi', '\\brm\\s+-rf\\b', 12, 1),

('phpinj.tag', 'phpinj', '<\\?php|<\\?=', 12, 1),
('phpinj.assert', 'phpinj', '\\bassert\\s*\\(', 12, 1),
('phpinj.base64_decode', 'phpinj', '\\bbase64_decode\\s*\\(', 12, 1),
('phpinj.serialize_unserialize', 'phpinj', '\\bunserialize\\s*\\(', 10, 1),
('phpinj.superglobal_exec', 'phpinj', '\\$_\\w+\\s*\\[[^\\]]+\\]\\s*\\(', 12, 1),

('ssrf.localhost', 'ssrf', '\\b(localhost|127\\.0\\.0\\.1|0\\.0\\.0\\.0|\\[::1\\])\\b', 8, 1),
('ssrf.private_net', 'ssrf', '\\b(10\\.\\d+\\.\\d+\\.\\d+|192\\.168\\.\\d+\\.\\d+|172\\.(1[6-9]|2\\d|3[01])\\.\\d+\\.\\d+)\\b', 8, 1),
('ssrf.metadata', 'ssrf', '169\\.254\\.169\\.254', 8, 1),
('ssrf.dict_gopher', 'ssrf', '\\b(dict|gopher|ldap)://', 8, 1),

('xxe.entity', 'xxe', '<!ENTITY', 10, 1),
('xxe.doctype', 'xxe', '<!DOCTYPE[^>]*\\[', 10, 1),
('xxe.system', 'xxe', 'SYSTEM\\s+["\']', 10, 1),

('crlf.newline', 'crlf', '(\\r\\n|\\r|\\n|%0d%0a|%0a|%0d)', 6, 0),
('crlf.setcookie', 'crlf', 'Set-Cookie\\s*:', 6, 1),

('redirect.absolute', 'redirect', '^https?://', 6, 0),
('redirect.protocol_relative', 'redirect', '^//', 6, 0),

('scanner.sqlmap', 'scanner', '\\b(sqlmap|havij|nikto|nmap|masscan|acunetix|nessus)\\b', 5, 1),
('scanner.dirbuster', 'scanner', '\\b(dirbuster|gobuster|wpscan|whatweb)\\b', 5, 1),
('scanner.empty_ua', 'scanner', '^$', 5, 1),
('scanner.curl_wget_ua', 'scanner', '^(curl|wget|python-requests|Go-http-client)/', 5, 1),
('scanner.common_exploit_path', 'scanner', '(/\\.env|/\\.git|/wp-login|/phpmyadmin|/adminer|/xmlrpc|/\\.svn|/backup\\.sql)', 5, 1),

('protocol.bad_method', 'protocol', '', 8, 0),
('protocol.bad_content_type', 'protocol', '', 8, 0),
('protocol.oversize_param', 'protocol', '', 6, 0),
('protocol.oversize_uri', 'protocol', '', 6, 0),
('protocol.null_byte', 'protocol', '%00', 6, 1);
