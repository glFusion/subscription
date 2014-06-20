var SUBSCR_xmlHttp;

function SUBSCR_toggleEnabled(newval, id, type, base_url)
{
  SUBSCR_xmlHttp = SUBSCR_GetXmlHttpObject();
  if (SUBSCR_xmlHttp==null) {
    alert ("Browser does not support HTTP Request")
    return
  }
  var url=base_url + "/admin/plugins/subscription/ajax.php?action=toggleEnabled";
  url=url+"&id="+id;
  url=url+"&type="+type;
  url=url+"&newval="+newval;
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
    xmlDoc=SUBSCR_xmlHttp.responseXML;
    id = xmlDoc.getElementsByTagName("id")[0].childNodes[0].nodeValue;
    imgurl = xmlDoc.getElementsByTagName("imgurl")[0].childNodes[0].nodeValue;
    baseurl = xmlDoc.getElementsByTagName("baseurl")[0].childNodes[0].nodeValue;
    type = xmlDoc.getElementsByTagName("type")[0].childNodes[0].nodeValue;
    if (xmlDoc.getElementsByTagName("newval")[0].childNodes[0].nodeValue == 1) {
        newval = 0;
    } else {
        newval = 1;
    }
    newhtml = 
        " <img src=\""+imgurl+"\" " +
        "style=\"display:inline; width:16px; height:16px;\" " +
        "onclick='SUBSCR_toggleEnabled("+newval+", \""+id+"\", \""+type+"\", \""+baseurl+"\");" +
        "' /> ";
    document.getElementById("togena"+id).innerHTML=newhtml;
  }

        //"width=\"16\" height=\"16\" " +
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

