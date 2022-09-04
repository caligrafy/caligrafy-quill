var env = null;

function loadEnvironment(environment) {
    env = JSON.parse(environment);
    apiKey = env.apiKey || null;
}


function loadVue(parameters) {
    var body = document.getElementsByTagName('body').item(0);
    var appName = env.appName? `/${env.appName}` : '';
    for(i = 0; i < parameters.scripts.length; i++) {
        var script = document.createElement('script');
        script.setAttribute('src', env.home + 'public' + appName + '/scripts/' + parameters.scripts[i] + '.js');
        body.appendChild(script);        
    }
    
}
