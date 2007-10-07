jQuery.fn.backgroundFade = function(s,callback) {
	var defaults = {
		sColor: [255,0,0],
		eColor: [255,255,255],
		fColor: null,
		steps: 200,
		intervals: 5,
		powr: 4
	},
	
	params = jQuery.extend(defaults,s);
	
	return this.each(function() {
		this.bgFade_sColor = jQuery.backgroundFade.parseHexColor(params.sColor);
		this.bgFade_eColor = jQuery.backgroundFade.parseHexColor(params.eColor);
		this.bgFade_fColor = params.fColor ? params.fColor : jQuery(this).css('backgroundColor');
		this.bgFade_steps = params.steps;
		this.bgFade_intervals = params.intervals;
		this.bgFade_powr = params.powr;
		this.bgFade_fn = callback;
		
		jQuery.backgroundFade.doFade(this);
	});
};

jQuery.backgroundFade = {
	parseHexColor: function(c) {
		if (c.constructor == String && c.substr(0,1) == '#') {
			return [parseInt(c.substr(1,2),16),parseInt(c.substr(3,2),16),parseInt(c.substr(5,2),16)];
		}
		return c;
	},
	
	easeInOut: function(minValue,maxValue,totalSteps,actualStep,powr) {
		var delta = maxValue - minValue;
		var stepp = minValue+(Math.pow(((1 / totalSteps)*actualStep),powr)*delta);
		return Math.ceil(stepp);
	},
	
	doFade: function(e) {
		if (e.bgFadeInt) window.clearInterval(e.bgFadeInt);
		var act_step = 0;
		
		e.bgFadeInt = window.setInterval(function() {
			e.style.backgroundColor =
			"rgb("+
			jQuery.backgroundFade.easeInOut(e.bgFade_sColor[0],e.bgFade_eColor[0],
				e.bgFade_steps,act_step,e.bgFade_powr)+","+
			jQuery.backgroundFade.easeInOut(e.bgFade_sColor[1],e.bgFade_eColor[1],
				e.bgFade_steps,act_step,e.bgFade_powr)+","+
			jQuery.backgroundFade.easeInOut(e.bgFade_sColor[2],e.bgFade_eColor[2],
				e.bgFade_steps,act_step,e.bgFade_powr)+")";
			
			act_step++;
			if (act_step > e.bgFade_steps) {
				window.clearInterval(e.bgFadeInt);
				e.bgFade_sColor = undefined;
				e.bgFade_eColor = undefined;
				e.bgFade_fColor = undefined;
				e.bgFade_steps = undefined;
				e.bgFade_intervals = undefined;
				e.bgFade_powr = undefined;
				if (typeof(e.bgFade_fn) == 'function') {
					e.bgFade_fn.call(e);
				}
				e.style.backgroundColor = e.bgFade_fColor;
			}
		},e.bgFade_intervals);
	}
};