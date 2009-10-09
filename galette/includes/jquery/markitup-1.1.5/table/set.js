// ----------------------------------------------------------------------------
// markItUp!
// ----------------------------------------------------------------------------
// Copyright (C) 2008 Jay Salvat
// http://markitup.jaysalvat.com/
// ----------------------------------------------------------------------------
mySettings = {	
	markupSet: [	
		{	name:'Table',
			openWith:'<table>',
			closeWith:'</table>',
			placeHolder:"<tr><(!(td|!|th)!)></(!(td|!|th)!)></tr>",
			className:'table' 
		},
		{	name:'Tr',
			openWith:'<tr>',
			closeWith:'</tr>',
			placeHolder:"<(!(td|!|th)!)></(!(td|!|th)!)>",
			className:'table-col'
		},
		{	name:'Td/Th',
			openWith:'<(!(td|!|th)!)>', 
			closeWith:'</(!(td|!|th)!)>',
			className:'table-row' 
		}
	]
}