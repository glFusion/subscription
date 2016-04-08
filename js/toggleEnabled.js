var SUBSCR_xmlHttp;

function SUBSCR_toggleEnabled(oldval, id, type, base_url)
{
  SUBSCR_xmlHttp = SUBSCR_GetXmlHttpObject();
  if (SUBSCR_xmlHttp==null) {
    alert ("Browser does not support HTTP Request")
    return
  }
  var url=base_url + "/admin/plugins/subscription/ajax.php?action=toggleEnabled";
  url=url+"&id="+id;
  url=url+"&type="+type;
  url=url+"&oldval="+oldval;
  url=url+"&sid="+Math.random();
  SUBSCR_xmlHttp.onreadystatechange=SUBSCR_sc_Enabled;
  SUBSCR_xmlHttp.open("GET",url,true);
  SUBSCR_xmlHttp.send(null);
}

function SUBSCR_sc_Enabled()
{
  var newstate;

  if (SUBSCR_xmlHttp.readyState==4 || SUBSCR_xmlHttp.readyState=="complete")
  {
    jsonObj = JSON.parse(SUBSCR_xmlHttp.responseText);
    id = jsonObj.id;
    baseurl = jsonObj.baseurl;
    type = jsonObj.type;
    newval = jsonObj.newval;
    document.getElementById("togena"+id).checked = jsonObj.newval == 1 ? true : false;
  }
}

function SUBSCR_GetXmlHttpObject()
{
  var objXMLHttp=null
  if (window.XMLHttpRequest)
  {
    objXMLHttp=new XMLHttpRequest()
  }
  else if (window.ActiveXObject)
  {
    objXMLHttp=new ActiveXObject("Microsoft.XMLHTTP")
  }
  return objXMLHttp
}

