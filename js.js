(function(){
var wrapper = document.querySelector('.page-content');
if ( ! wrapper ) return;
wrapper.style.opacity='1';
wrapper.style.transition='.3s';

var c1 = 'current-menu-item', c2 = 'current_page_item';

function replace(){
	var r = {
		html: wrapper.innerHTML,
		href: location.href,
		slug: location.pathname.replace(/\//g,''),
		title: document.title,
		id: document.body.className.split('page-id-')[1].split(' ')[0],
//		li: document.querySelector('.'+c1).id,
	};
	history.replaceState(r,'',r.href)
}

window.onpopstate = function(e){
	if (e.state) process(e.state);// a var was: document.querySelector('#'+e.state.li+' a')
	else if (location.hash) replace();
	else location.reload();
};

function getPage(e){
	e.preventDefault();
	wrapper.style.opacity='0';
	document.documentElement.classList.remove('dopen');
    document.body.classList.remove('mnav-open');
	var r,
	a = this,
	slug = this.pathname.replace(/\//g,''),
	x = new XMLHttpRequest();
	x.open('GET','/wp-json/mnmlajax/v1/load/?slug='+slug);
	x.onload = function(){
		r=JSON.parse(x.response);
		if ( r && r.html ){
		    r.href = a.href;
		    r.slug = slug;
//		    r.li = a.parentElement.id;
			process(r);
			history.pushState(r,'',r.href);
		} else {
			console.log(a.href);
			// location = a.href;
		}
	};
	x.send();

}

function addListener(l){
	for(var i=0; i<l.length; ++i) l[i].addEventListener('click',getPage);
}

function process(r) {
	wrapper.innerHTML = r.html;
	scroll(0,0);
	r.slug ? document.body.classList.remove('home') : document.body.classList.add('home');
	document.body.className = document.body.className.replace(/id-\d+? /, 'id-'+r.id+' ');
	var cur = document.querySelector('.'+c1);
	if(cur){
		cur.classList.remove(c1);
		cur.classList.remove(c2);
	}
	// a.parentElement.className += ' '+c1+' '+c2;
	var l = document.querySelector('#primary-menu [href="'+r.href+'"]');
	if(l) l.parentElement.className += ' '+c1+' '+c2;
	document.title = r.title;
	var scripts = wrapper.getElementsByTagName('script');
	for ( var i=0; i < scripts.length; ++i ){
		var script = document.createElement('script');
		script.innerHTML = scripts[i].innerHTML;
		wrapper.removeChild(scripts[i]);
		wrapper.appendChild(script);
	}
	addListener( wrapper.querySelectorAll('a[href*="'+location.host+'"]') );
	wrapper.style.opacity='1';
}

// initialize
replace();
addListener( document.querySelectorAll('a[href*="'+location.host+'"]:not(.ab-item)') );

})();