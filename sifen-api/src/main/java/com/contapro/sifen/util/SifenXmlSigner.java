package com.contapro.sifen.util;

import org.apache.xml.security.Init;
import org.w3c.dom.Document;
import org.w3c.dom.Element;
import org.w3c.dom.NodeList;

import javax.xml.crypto.dsig.*;
import javax.xml.crypto.dsig.dom.DOMSignContext;
import javax.xml.crypto.dsig.keyinfo.KeyInfo;
import javax.xml.crypto.dsig.keyinfo.KeyInfoFactory;
import javax.xml.crypto.dsig.keyinfo.X509Data;
import javax.xml.crypto.dsig.spec.C14NMethodParameterSpec;
import javax.xml.crypto.dsig.spec.TransformParameterSpec;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.transform.OutputKeys;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerFactory;
import javax.xml.transform.dom.DOMSource;
import javax.xml.transform.stream.StreamResult;
import java.io.ByteArrayInputStream;
import java.io.FileInputStream;
import java.io.InputStream;
import java.io.StringWriter;
import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import java.security.MessageDigest;
import java.security.PrivateKey;
import java.security.cert.X509Certificate;
import java.util.Base64;
import java.util.Collections;
import java.util.Enumeration;
import java.util.List;

/**
 * Firma Digital XMLDSig (RSA-SHA256) sobre el nodo <DE Id="{cdc}"> y generación
 * del enlace QR oficial de SIFEN v150 (<gCamFuFD><dCarQR>) con hash SHA-256 + CSC.
 */
public class SifenXmlSigner {

    static {
        Init.init();
    }

    public static class SignedResult {
        public final String signedXml;
        public final String qrUrl;
        public final String digestValueHex;
        public final boolean firmadoConCertificadoReal;

        public SignedResult(String signedXml, String qrUrl, String digestValueHex, boolean firmadoConCertificadoReal) {
            this.signedXml = signedXml;
            this.qrUrl = qrUrl;
            this.digestValueHex = digestValueHex;
            this.firmadoConCertificadoReal = firmadoConCertificadoReal;
        }
    }

    /**
     * Carga un KeyStore PKCS12 desde Base64 o desde una ruta de archivo.
     */
    public static KeyStore cargarKeyStore(String certBase64, String certPath, String password) throws Exception {
        KeyStore ks = KeyStore.getInstance("PKCS12");
        char[] passChars = password != null ? password.toCharArray() : new char[0];

        if (certBase64 != null && !certBase64.isBlank()) {
            String cleanB64 = certBase64.trim();
            if (cleanB64.contains(",")) {
                cleanB64 = cleanB64.substring(cleanB64.indexOf(',') + 1);
            }
            byte[] p12Bytes = Base64.getDecoder().decode(cleanB64.replaceAll("\\s+", ""));
            try (InputStream is = new ByteArrayInputStream(p12Bytes)) {
                ks.load(is, passChars);
            }
            return ks;
        }

        if (certPath != null && !certPath.isBlank()) {
            try (InputStream is = new FileInputStream(certPath.trim())) {
                ks.load(is, passChars);
            }
            return ks;
        }

        return null;
    }

    /**
     * Firma el XML <rDE> sobre su nodo <DE Id="{cdc}"> y agrega el nodo <gCamFuFD><dCarQR> con el hash QR.
     */
    public static SignedResult firmarDeYGenerarQr(
            SifenXmlBuilder.BuiltDe built,
            String certBase64,
            String certPath,
            String certPassword,
            String idCsc,
            String csc,
            String baseQrUrl
    ) throws Exception {

        DocumentBuilderFactory dbf = DocumentBuilderFactory.newInstance();
        dbf.setNamespaceAware(true);
        Document doc = dbf.newDocumentBuilder().parse(
                new ByteArrayInputStream(built.xmlUnsigned.getBytes(StandardCharsets.UTF_8))
        );

        NodeList deList = doc.getElementsByTagName("DE");
        if (deList.getLength() == 0) {
            throw new IllegalStateException("No se encontró el nodo <DE> en el XML generado.");
        }
        Element deElement = (Element) deList.item(0);
        deElement.setIdAttribute("Id", true);

        boolean firmadoReal = false;
        String digestValueStr = "";

        KeyStore ks = null;
        try {
            ks = cargarKeyStore(certBase64, certPath, certPassword);
        } catch (Exception ignored) {
            ks = null;
        }

        if (ks != null) {
            String alias = null;
            Enumeration<String> aliases = ks.aliases();
            while (aliases.hasMoreElements()) {
                String a = aliases.nextElement();
                if (ks.isKeyEntry(a)) {
                    alias = a;
                    break;
                }
            }
            if (alias == null && ks.aliases().hasMoreElements()) {
                alias = ks.aliases().nextElement();
            }

            if (alias != null) {
                char[] passChars = certPassword != null ? certPassword.toCharArray() : new char[0];
                PrivateKey privateKey = (PrivateKey) ks.getKey(alias, passChars);
                X509Certificate cert = (X509Certificate) ks.getCertificate(alias);

                XMLSignatureFactory fac = XMLSignatureFactory.getInstance("DOM");
                List<Transform> transforms = List.of(
                        fac.newTransform(Transform.ENVELOPED, (TransformParameterSpec) null),
                        fac.newTransform(CanonicalizationMethod.EXCLUSIVE, (TransformParameterSpec) null)
                );

                Reference ref = fac.newReference(
                        "#" + built.cdc,
                        fac.newDigestMethod(DigestMethod.SHA256, null),
                        transforms,
                        null,
                        null
                );

                SignedInfo si = fac.newSignedInfo(
                        fac.newCanonicalizationMethod(CanonicalizationMethod.EXCLUSIVE, (C14NMethodParameterSpec) null),
                        fac.newSignatureMethod(SignatureMethod.RSA_SHA256, null),
                        Collections.singletonList(ref)
                );

                KeyInfoFactory kif = fac.getKeyInfoFactory();
                X509Data x509Data = kif.newX509Data(Collections.singletonList(cert));
                KeyInfo ki = kif.newKeyInfo(Collections.singletonList(x509Data));

                // La firma <Signature> se coloca como hermano inmediatamente posterior a <DE> dentro de <rDE>
                DOMSignContext signContext = new DOMSignContext(privateKey, doc.getDocumentElement());
                XMLSignature signature = fac.newXMLSignature(si, ki);
                signature.sign(signContext);

                NodeList dvNodes = doc.getElementsByTagNameNS(XMLSignature.XMLNS, "DigestValue");
                if (dvNodes.getLength() > 0) {
                    digestValueStr = dvNodes.item(0).getTextContent().trim();
                }
                firmadoReal = true;
            }
        }

        if (digestValueStr.isEmpty()) {
            // Digest determinístico para modo prueba sin certificado .p12 cargado
            byte[] sha = MessageDigest.getInstance("SHA-256").digest(built.xmlUnsigned.getBytes(StandardCharsets.UTF_8));
            digestValueStr = Base64.getEncoder().encodeToString(sha);
        }

        // Construcción del parámetro QR oficial SIFEN v150
        String fechaHex = toHexString(built.fechaEmisionIso);
        String digValHex = toHexString(digestValueStr);
        String idCscClean = (idCsc != null && !idCsc.isBlank()) ? CdcUtil.leftPadDigits(idCsc, 4) : "0001";
        String cscClean = (csc != null && !csc.isBlank()) ? csc.trim() : "ABCD0000000000000000000000000000";

        StringBuilder qrParams = new StringBuilder();
        qrParams.append("nVersion=150");
        qrParams.append("&Id=").append(built.cdc);
        qrParams.append("&dFeEmiDE=").append(fechaHex);
        if (built.receptorContribuyente) {
            qrParams.append("&dRucRec=").append(built.rucReceptorSinDv);
        } else {
            qrParams.append("&dNumIDRec=").append(built.numDocReceptor);
        }
        qrParams.append("&dTotGralOpe=").append(built.totalGeneral.toPlainString());
        qrParams.append("&dTotIVA=").append(built.totalIva.toPlainString());
        qrParams.append("&cItems=").append(built.cantidadItems);
        qrParams.append("&DigestValue=").append(digValHex);
        qrParams.append("&IdCSC=").append(idCscClean);

        String cHashQr = sha256Hex(qrParams.toString() + cscClean);
        String fullQrUrl = baseQrUrl + qrParams + "&cHashQR=" + cHashQr;

        // Añadir nodo <gCamFuFD><dCarQR> al final de <rDE>
        Element gCamFuFD = doc.createElementNS("http://ekuatia.set.gov.py/sifen/xsd", "gCamFuFD");
        Element dCarQR = doc.createElementNS("http://ekuatia.set.gov.py/sifen/xsd", "dCarQR");
        dCarQR.setTextContent(fullQrUrl);
        gCamFuFD.appendChild(dCarQR);
        doc.getDocumentElement().appendChild(gCamFuFD);

        String finalXml = documentToString(doc);
        return new SignedResult(finalXml, fullQrUrl, digValHex, firmadoReal);
    }

    public static String documentToString(Document doc) throws Exception {
        Transformer tf = TransformerFactory.newInstance().newTransformer();
        tf.setOutputProperty(OutputKeys.OMIT_XML_DECLARATION, "no");
        tf.setOutputProperty(OutputKeys.ENCODING, "UTF-8");
        tf.setOutputProperty(OutputKeys.INDENT, "no");
        StringWriter sw = new StringWriter();
        tf.transform(new DOMSource(doc), new StreamResult(sw));
        return sw.toString();
    }

    public static String toHexString(String input) {
        byte[] bytes = input.getBytes(StandardCharsets.UTF_8);
        StringBuilder sb = new StringBuilder(bytes.length * 2);
        for (byte b : bytes) {
            sb.append(String.format("%02x", b));
        }
        return sb.toString();
    }

    public static String sha256Hex(String input) throws Exception {
        byte[] hash = MessageDigest.getInstance("SHA-256").digest(input.getBytes(StandardCharsets.UTF_8));
        StringBuilder sb = new StringBuilder(hash.length * 2);
        for (byte b : hash) {
            sb.append(String.format("%02x", b));
        }
        return sb.toString();
    }
}
