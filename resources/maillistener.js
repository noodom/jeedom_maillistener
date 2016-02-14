var MailListener = require("./index.js");

var email = '';
var username = '';
var password = '';
var server = '';
var urlJeedom = '';
var port = '';
var attach = '';
var path = '';

var request = require('request');

process.env.NODE_TLS_REJECT_UNAUTHORIZED = "0";

// print process.argv
process.argv.forEach(function(val, index, array) {

	switch ( index ) {
		case 2 : email = val; break;
		case 3 : urlJeedom = val; break;
		case 4 : username = val; break;
		case 5 : password = val; break;
		case 6 : server = val; break;
		case 7 : port = val; break;
		case 8 : attach = val; break;
		case 9 : path = val; break;
	}

});

if (attach == true) {
	var mailListener = new MailListener({
		username: username,
		password: password,
		host: server,
		port: port,
		tls: true,
		tlsOptions: { rejectUnauthorized: false },
		mailbox: "INBOX",
		markSeen: true,
		fetchUnreadOnStart: true,
		attachments: true,
		attachmentOptions: { directory: path }
	});
} else {
	var mailListener = new MailListener({
		username: username,
		password: password,
		host: server,
		port: port,
		tls: true,
		tlsOptions: { rejectUnauthorized: false },
		mailbox: "INBOX",
		markSeen: true,
		fetchUnreadOnStart: true,
		attachments: false
	});
}


mailListener.start();

mailListener.on("server:connected", function(){
	console.log("imapConnected");
});

mailListener.on("server:disconnected", function(){
	console.log("imapDisconnected");
});

mailListener.on("error", function(err){
	console.log(err);
});

mailListener.on("mail", function(mail){
	//console.log(mail);
	//console.log("From:", mail.from); //[{address:'sender@example.com',name:'Sender Name'}]
	//console.log("Subject:", mail.subject); // Hello world!
	//console.log("Text body:", mail.text); // How are you today?

	apiurl = urlJeedom+'&type=maillistener&messagetype=mailIncoming&email='+email+'&from='+mail.from[0].address+'&subject='+mail.subject;
	//console.log(apiurl);
	request({
		url: apiurl,
		method: 'PUT',
		json: {"body": mail.text, "html": mail.html},
	},
	function (error, response, body) {
		if (!error && response.statusCode == 200) {
			console.log('contact Jeedom avec retour :',response.statusCode);
		}
	});
});

mailListener.on("attachment", function(attachment){
	console.log(attachment.path);
//	var output = fs.createWriteStream(attachment.path);
  //attachment.stream.pipe(output);
	apiurl = urlJeedom+'&type=maillistener&messagetype=attachment&email='+email+'&value='+attachment.path;
	request(apiurl, function (error, response, body) {
if (!error && response.statusCode == 200) {
console.log('attachement retour:',response.statusCode);
}
});
});
