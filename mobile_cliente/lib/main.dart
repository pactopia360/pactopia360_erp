import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:file_picker/file_picker.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const PactopiaClienteApp());
}

class PactopiaClienteApp extends StatelessWidget {
  const PactopiaClienteApp({super.key});

  @override
  Widget build(BuildContext context) {
    const seedColor = Color(0xFF0F172A);

    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'PACTOPIA360 Cliente',
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: seedColor,
          brightness: Brightness.light,
        ),
        scaffoldBackgroundColor: const Color(0xFFF8FAFC),
        textTheme: GoogleFonts.interTextTheme(),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 16,
            vertical: 14,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFF0F172A), width: 1.2),
          ),
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            elevation: 0,
            minimumSize: const Size.fromHeight(52),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
        cardTheme: CardThemeData(
          color: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
            side: const BorderSide(color: Color(0xFFE2E8F0)),
          ),
        ),
      ),
      home: const SplashGate(),
    );
  }
}

///
/// AJUSTA ESTA URL SEGÚN TU ENTORNO:
///
/// Android emulator -> http://10.0.2.2:8000/api/v1/mobile
/// iOS simulator    -> http://127.0.0.1:8000/api/v1/mobile
/// Dispositivo real -> http://TU_IP_LOCAL:8000/api/v1/mobile
///
class ApiConfig {
  static const String baseUrl = 'http://10.0.2.2:8000/api/v1/mobile';
}

class AppStorage {
  AppStorage._();

  static final FlutterSecureStorage _storage = const FlutterSecureStorage();

  static const String tokenKey = 'p360_mobile_token';
  static const String userNameKey = 'p360_mobile_user_name';
  static const String userEmailKey = 'p360_mobile_user_email';

  static Future<void> saveSession({
    required String token,
    String? userName,
    String? userEmail,
  }) async {
    await _storage.write(key: tokenKey, value: token);

    if ((userName ?? '').trim().isNotEmpty) {
      await _storage.write(key: userNameKey, value: userName);
    }

    if ((userEmail ?? '').trim().isNotEmpty) {
      await _storage.write(key: userEmailKey, value: userEmail);
    }
  }

  static Future<String?> getToken() {
    return _storage.read(key: tokenKey);
  }

  static Future<String?> getUserName() {
    return _storage.read(key: userNameKey);
  }

  static Future<String?> getUserEmail() {
    return _storage.read(key: userEmailKey);
  }

  static Future<void> clearSession() async {
    await _storage.delete(key: tokenKey);
    await _storage.delete(key: userNameKey);
    await _storage.delete(key: userEmailKey);
  }
}

class ApiClient {
  ApiClient._();

  static final Dio _dio = Dio(
    BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 20),
      sendTimeout: const Duration(seconds: 20),
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ),
  );

  static Future<Map<String, dynamic>> ping() async {
    final response = await _dio.get('/ping');
    return _asMap(response.data);
  }

  static Future<LoginResult> login({
    required String login,
    required String password,
    required String deviceName,
  }) async {
    try {
      final response = await _dio.post(
        '/auth/login',
        data: {'login': login, 'password': password, 'device_name': deviceName},
      );

      final body = _asMap(response.data);
      final ok = body['ok'] == true;

      if (!ok) {
        return LoginResult.failure(
          message: _messageFromBody(
            body,
            fallback: 'No se pudo iniciar sesión.',
          ),
          raw: body,
        );
      }

      final data = _asMap(body['data']);
      final user = _asMap(data['user']);
      final token = (data['token'] ?? '').toString();

      if (token.trim().isEmpty) {
        return LoginResult.failure(
          message: 'La API no devolvió token de acceso.',
          raw: body,
        );
      }

      return LoginResult.success(
        token: token,
        userName: (user['nombre'] ?? user['name'] ?? '').toString(),
        userEmail: (user['email'] ?? '').toString(),
        raw: body,
      );
    } on DioException catch (e) {
      final body = _asMap(e.response?.data);
      return LoginResult.failure(
        message: _messageFromBody(
          body,
          fallback: _dioErrorMessage(e, 'No se pudo conectar con el servidor.'),
        ),
        raw: body,
      );
    } catch (_) {
      return LoginResult.failure(
        message: 'Ocurrió un error inesperado al iniciar sesión.',
        raw: const {},
      );
    }
  }

  static Future<Map<String, dynamic>> me(String token) async {
    final response = await _dio.get(
      '/auth/me',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<Map<String, dynamic>> dashboard(String token) async {
    final response = await _dio.get(
      '/dashboard',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<Map<String, dynamic>> satDashboard(String token) async {
    final response = await _dio.get(
      '/sat/dashboard',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<Map<String, dynamic>> profile(String token) async {
    final response = await _dio.get(
      '/account/profile',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<List<MobilePaymentItem>> payments(String token) async {
    final response = await _dio.get(
      '/account/payments',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    final body = _asMap(response.data);
    final data = _asMap(body['data']);
    final rows = _asList(data['rows']);

    return rows
        .map((item) => MobilePaymentItem.fromMap(_asMap(item)))
        .toList(growable: false);
  }

  static Future<Map<String, dynamic>> billingStatement(String token) async {
    final response = await _dio.get(
      '/billing/statement',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<Map<String, dynamic>> billingPdfUrl(
    String token,
    String period,
  ) async {
    final response = await _dio.get(
      '/billing/statement/$period/pdf-url',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<Map<String, dynamic>> billingPayUrl(
    String token,
    String period,
  ) async {
    final response = await _dio.get(
      '/billing/statement/$period/pay-url',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<Map<String, dynamic>> billingRequestInvoice(
    String token,
    String period,
  ) async {
    final response = await _dio.post(
      '/billing/statement/$period/invoice-request',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<List<MobileInvoiceItem>> invoices(String token) async {
    final response = await _dio.get(
      '/invoices',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    final body = _asMap(response.data);
    final data = _asMap(body['data']);
    final rows = _asList(data['rows']);

    return rows
        .map((item) => MobileInvoiceItem.fromMap(_asMap(item)))
        .toList(growable: false);
  }

  static Future<Map<String, dynamic>> invoiceDownloadUrl(
    String token,
    String id,
  ) async {
    final response = await _dio.get(
      '/invoices/$id/download-url',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<QuotesResponse> quotes(
    String token, {
    String? rfc,
    String? status,
    int page = 1,
    int perPage = 20,
  }) async {
    final query = <String, dynamic>{'page': page, 'per_page': perPage};

    if ((rfc ?? '').trim().isNotEmpty) {
      query['rfc'] = rfc!.trim().toUpperCase();
    }

    if ((status ?? '').trim().isNotEmpty) {
      query['status'] = status!.trim().toLowerCase();
    }

    final response = await _dio.get(
      '/sat/quotes',
      queryParameters: query,
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    final body = _asMap(response.data);
    final data = _asMap(body['data']);
    final itemsRaw = _asList(data['items']);
    final items = itemsRaw
        .map((item) => MobileQuote.fromMap(_asMap(item)))
        .toList(growable: false);

    final pagination = _asMap(data['pagination']);

    return QuotesResponse(
      items: items,
      currentPage: _toInt(pagination['current_page'], fallback: page),
      perPage: _toInt(pagination['per_page'], fallback: perPage),
      total: _toInt(pagination['total']),
      lastPage: _toInt(pagination['last_page'], fallback: 1),
      hasMore: _toBool(pagination['has_more']),
    );
  }

  static Future<MobileQuote> quoteDetail(String token, String id) async {
    final response = await _dio.get(
      '/sat/quotes/$id',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    final body = _asMap(response.data);
    final data = _asMap(body['data']);

    return MobileQuote.fromMap(data);
  }

  static Future<void> logout(String token) async {
    try {
      await _dio.post(
        '/auth/logout',
        options: Options(headers: {'Authorization': 'Bearer $token'}),
      );
    } catch (_) {
      // Si falla el logout remoto, igual limpiamos la sesión local.
    }
  }

  static Future<Map<String, dynamic>> quoteCheckout(
    String token,
    String id,
  ) async {
    final response = await _dio.post(
      '/sat/quotes/$id/checkout',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    return _asMap(response.data);
  }

  static Future<Map<String, dynamic>> quoteTransferProof(
    String token,
    String id, {
    required String reference,
    required String transferDate,
    required String transferAmount,
    required String proofFilePath,
    String payerName = '',
    String payerBank = '',
    String notes = '',
  }) async {
    final formData = FormData.fromMap({
      'reference': reference,
      'transfer_date': transferDate,
      'transfer_amount': transferAmount,
      'payer_name': payerName,
      'payer_bank': payerBank,
      'notes': notes,
      'proof_file': await MultipartFile.fromFile(proofFilePath),
    });

    final response = await _dio.post(
      '/sat/quotes/$id/transfer-proof',
      data: formData,
      options: Options(
        headers: {'Authorization': 'Bearer $token'},
        contentType: 'multipart/form-data',
      ),
    );

    return _asMap(response.data);
  }

  static Future<MobileQuote> createQuote(
    String token, {
    required String rfc,
    required String tipoSolicitud,
    required int xmlCount,
    required String dateFrom,
    required String dateTo,
    String concepto = '',
    String discountCode = '',
    bool includeIva = true,
  }) async {
    final response = await _dio.post(
      '/sat/quotes',
      data: {
        'rfc': rfc.trim().toUpperCase(),
        'tipo': tipoSolicitud.trim().toLowerCase(),
        'xml_count': xmlCount,
        'date_from': dateFrom.trim(),
        'date_to': dateTo.trim(),
        'notes': concepto.trim(),
        'discount_code': discountCode.trim(),
        'iva': includeIva ? 1 : 0,
      },
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    final body = _asMap(response.data);
    final data = _asMap(body['data']);

    return MobileQuote.fromMap(data);
  }

  static Future<SatQuickCalcResult> quickCalc(
    String token, {
    required String rfc,
    required String tipoSolicitud,
    required int xmlCount,
    required String dateFrom,
    required String dateTo,
    String concepto = '',
    String discountCode = '',
    bool includeIva = true,
  }) async {
    final response = await _dio.post(
      '/sat/quotes/quick-calc',
      data: {
        'xml_count': xmlCount,
        'discount_code': discountCode.trim(),
        'iva': includeIva ? 1 : 0,
      },
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    final body = _asMap(response.data);
    final data = _asMap(body['data']);

    return SatQuickCalcResult.fromMap(data);
  }

  static Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) {
      return value.map((key, val) => MapEntry(key.toString(), val));
    }
    return <String, dynamic>{};
  }

  static List<dynamic> _asList(dynamic value) {
    if (value is List) return value;
    return <dynamic>[];
  }

  static int _toInt(dynamic value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is double) return value.toInt();
    if (value is String) return int.tryParse(value) ?? fallback;
    return fallback;
  }

  static double? _toDoubleNullable(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }

  static bool _toBool(dynamic value) {
    if (value is bool) return value;
    if (value is num) return value != 0;
    if (value is String) {
      final v = value.trim().toLowerCase();
      return v == '1' || v == 'true' || v == 'yes';
    }
    return false;
  }

  static String _messageFromBody(
    Map<String, dynamic> body, {
    required String fallback,
  }) {
    final direct = (body['msg'] ?? body['message'] ?? '').toString().trim();
    if (direct.isNotEmpty) return direct;

    final errors = body['errors'];
    if (errors is Map) {
      for (final entry in errors.entries) {
        final value = entry.value;
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }
        if (value != null) {
          return value.toString();
        }
      }
    }

    return fallback;
  }

  static String _dioErrorMessage(DioException e, String fallback) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
        return 'Tiempo de conexión agotado.';
      case DioExceptionType.sendTimeout:
        return 'Tiempo de envío agotado.';
      case DioExceptionType.receiveTimeout:
        return 'Tiempo de respuesta agotado.';
      case DioExceptionType.connectionError:
        return 'No se pudo conectar con la API.';
      case DioExceptionType.badResponse:
        return 'La API respondió con error.';
      case DioExceptionType.cancel:
        return 'La solicitud fue cancelada.';
      case DioExceptionType.badCertificate:
        return 'Certificado inválido.';
      case DioExceptionType.unknown:
        return fallback;
    }
  }
}

Future<bool> openExternalUrl(BuildContext context, String rawUrl) async {
  final url = rawUrl.trim();
  if (url.isEmpty) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('La URL está vacía.')));
    return false;
  }

  final uri = Uri.tryParse(url);
  if (uri == null) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('La URL no es válida.')));
    return false;
  }

  final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);

  if (!ok && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('No se pudo abrir el enlace.')),
    );
  }

  return ok;
}

Color satStatusColor(String status) {
  final s = status.trim().toLowerCase();

  switch (s) {
    case 'cotizada':
      return const Color(0xFF1D4ED8);
    case 'en_revision_pago':
      return const Color(0xFFD97706);
    case 'pagada':
      return const Color(0xFF059669);
    case 'en_descarga':
      return const Color(0xFF7C3AED);
    case 'completada':
      return const Color(0xFF166534);
    case 'cancelada':
      return const Color(0xFFB91C1C);
    case 'borrador':
      return const Color(0xFF64748B);
    case 'en_proceso':
      return const Color(0xFF0EA5E9);
    default:
      return const Color(0xFF334155);
  }
}

class LoginResult {
  final bool ok;
  final String message;
  final String token;
  final String userName;
  final String userEmail;
  final Map<String, dynamic> raw;

  const LoginResult({
    required this.ok,
    required this.message,
    required this.token,
    required this.userName,
    required this.userEmail,
    required this.raw,
  });

  factory LoginResult.success({
    required String token,
    required String userName,
    required String userEmail,
    required Map<String, dynamic> raw,
  }) {
    return LoginResult(
      ok: true,
      message: 'Login correcto.',
      token: token,
      userName: userName,
      userEmail: userEmail,
      raw: raw,
    );
  }

  factory LoginResult.failure({
    required String message,
    required Map<String, dynamic> raw,
  }) {
    return LoginResult(
      ok: false,
      message: message,
      token: '',
      userName: '',
      userEmail: '',
      raw: raw,
    );
  }
}

class QuotesResponse {
  final List<MobileQuote> items;
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  const QuotesResponse({
    required this.items,
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.hasMore,
  });
}

class MobilePaymentItem {
  final String id;
  final String concept;
  final String status;
  final String period;
  final String provider;
  final String createdAt;
  final double amountMxn;

  const MobilePaymentItem({
    required this.id,
    required this.concept,
    required this.status,
    required this.period,
    required this.provider,
    required this.createdAt,
    required this.amountMxn,
  });

  factory MobilePaymentItem.fromMap(Map<String, dynamic> map) {
    return MobilePaymentItem(
      id: (map['id'] ?? '').toString(),
      concept: (map['concept'] ?? map['descripcion'] ?? 'Pago').toString(),
      status: (map['status'] ?? '').toString(),
      period: (map['period'] ?? '').toString(),
      provider: (map['provider'] ?? '').toString(),
      createdAt: (map['created_at'] ?? '').toString(),
      amountMxn:
          ApiClient._toDoubleNullable(
            map['amount_mxn'] ?? map['amount'] ?? map['monto_mxn'],
          ) ??
          0.0,
    );
  }

  String get amountLabel => '\$${amountMxn.toStringAsFixed(2)} MXN';
}

class MobileInvoiceItem {
  final String id;
  final String period;
  final String status;
  final bool hasZip;
  final String createdAt;
  final String updatedAt;

  const MobileInvoiceItem({
    required this.id,
    required this.period,
    required this.status,
    required this.hasZip,
    required this.createdAt,
    required this.updatedAt,
  });

  factory MobileInvoiceItem.fromMap(Map<String, dynamic> map) {
    return MobileInvoiceItem(
      id: (map['id'] ?? '').toString(),
      period: (map['period'] ?? '').toString(),
      status: (map['status'] ?? '').toString(),
      hasZip: ApiClient._toBool(map['has_zip']),
      createdAt: (map['created_at'] ?? '').toString(),
      updatedAt: (map['updated_at'] ?? '').toString(),
    );
  }
}

class MobileBillingRow {
  final String period;
  final String status;
  final double charge;
  final double paidAmount;
  final double saldo;
  final bool canPay;
  final String periodRange;
  final String rfc;
  final String alias;
  final String invoiceRequestStatus;
  final bool invoiceHasZip;
  final String priceSource;

  const MobileBillingRow({
    required this.period,
    required this.status,
    required this.charge,
    required this.paidAmount,
    required this.saldo,
    required this.canPay,
    required this.periodRange,
    required this.rfc,
    required this.alias,
    required this.invoiceRequestStatus,
    required this.invoiceHasZip,
    required this.priceSource,
  });

  factory MobileBillingRow.fromMap(Map<String, dynamic> map) {
    return MobileBillingRow(
      period: (map['period'] ?? '').toString(),
      status: (map['status'] ?? '').toString(),
      charge: ApiClient._toDoubleNullable(map['charge']) ?? 0.0,
      paidAmount: ApiClient._toDoubleNullable(map['paid_amount']) ?? 0.0,
      saldo: ApiClient._toDoubleNullable(map['saldo']) ?? 0.0,
      canPay: ApiClient._toBool(map['can_pay']),
      periodRange: (map['period_range'] ?? '').toString(),
      rfc: (map['rfc'] ?? '').toString(),
      alias: (map['alias'] ?? '').toString(),
      invoiceRequestStatus: (map['invoice_request_status'] ?? '').toString(),
      invoiceHasZip: ApiClient._toBool(map['invoice_has_zip']),
      priceSource: (map['price_source'] ?? '').toString(),
    );
  }

  String get chargeLabel => '\$${charge.toStringAsFixed(2)} MXN';
  String get saldoLabel => '\$${saldo.toStringAsFixed(2)} MXN';
  String get paidLabel => '\$${paidAmount.toStringAsFixed(2)} MXN';
}

class SatQuickCalcResult {
  final int xmlCount;
  final double subtotal;
  final double iva;
  final double total;
  final bool includeIva;
  final String priceSource;
  final String concept;
  final String tipoSolicitud;
  final String rfc;
  final String dateFrom;
  final String dateTo;
  final String discountCode;

  const SatQuickCalcResult({
    required this.xmlCount,
    required this.subtotal,
    required this.iva,
    required this.total,
    required this.includeIva,
    required this.priceSource,
    required this.concept,
    required this.tipoSolicitud,
    required this.rfc,
    required this.dateFrom,
    required this.dateTo,
    required this.discountCode,
  });

  factory SatQuickCalcResult.fromMap(Map<String, dynamic> map) {
    return SatQuickCalcResult(
      xmlCount: ApiClient._toInt(map['xml_count']),
      subtotal: ApiClient._toDoubleNullable(map['subtotal']) ?? 0,
      iva: ApiClient._toDoubleNullable(map['iva_amount'] ?? map['iva']) ?? 0,
      total: ApiClient._toDoubleNullable(map['total']) ?? 0,
      includeIva: ApiClient._toBool(map['include_iva'] ?? map['iva']),
      priceSource: (map['price_source'] ?? '').toString(),
      concept: (map['concept'] ?? map['concepto'] ?? map['notes'] ?? '')
          .toString(),
      tipoSolicitud: (map['tipo_solicitud'] ?? map['tipo'] ?? '').toString(),
      rfc: (map['rfc'] ?? '').toString(),
      dateFrom: (map['date_from'] ?? '').toString(),
      dateTo: (map['date_to'] ?? '').toString(),
      discountCode: (map['discount_code'] ?? '').toString(),
    );
  }

  String get subtotalLabel => '\$${subtotal.toStringAsFixed(2)} MXN';
  String get ivaLabel => '\$${iva.toStringAsFixed(2)} MXN';
  String get totalLabel => '\$${total.toStringAsFixed(2)} MXN';
}

class MobileQuote {
  final String id;
  final String folio;
  final String rfc;
  final String razonSocial;
  final String tipo;
  final String concepto;
  final String statusDb;
  final String statusUi;
  final String statusLabel;
  final int progress;
  final bool canPay;
  final String customerAction;
  final double? importeEstimado;
  final double? subtotal;
  final double? iva;
  final double? total;
  final int xmlCount;
  final int cfdiCount;
  final String dateFrom;
  final String dateTo;
  final String validUntil;
  final String priceSource;
  final String discountCode;
  final String paidAt;
  final String createdAt;
  final String updatedAt;
  final Map<String, dynamic>? transferReview;
  final Map<String, dynamic> meta;

  const MobileQuote({
    required this.id,
    required this.folio,
    required this.rfc,
    required this.razonSocial,
    required this.tipo,
    required this.concepto,
    required this.statusDb,
    required this.statusUi,
    required this.statusLabel,
    required this.progress,
    required this.canPay,
    required this.customerAction,
    required this.importeEstimado,
    required this.subtotal,
    required this.iva,
    required this.total,
    required this.xmlCount,
    required this.cfdiCount,
    required this.dateFrom,
    required this.dateTo,
    required this.validUntil,
    required this.priceSource,
    required this.discountCode,
    required this.paidAt,
    required this.createdAt,
    required this.updatedAt,
    required this.transferReview,
    required this.meta,
  });

  factory MobileQuote.fromMap(Map<String, dynamic> map) {
    Map<String, dynamic>? transfer;
    final rawTransfer = map['transfer_review'];
    if (rawTransfer is Map<String, dynamic>) {
      transfer = rawTransfer;
    } else if (rawTransfer is Map) {
      transfer = rawTransfer.map((k, v) => MapEntry(k.toString(), v));
    }

    return MobileQuote(
      id: (map['id'] ?? '').toString(),
      folio: (map['folio'] ?? '').toString(),
      rfc: (map['rfc'] ?? '').toString(),
      razonSocial: (map['razon_social'] ?? '').toString(),
      tipo: (map['tipo'] ?? '').toString(),
      concepto: (map['concepto'] ?? '').toString(),
      statusDb: (map['status_db'] ?? '').toString(),
      statusUi: (map['status_ui'] ?? '').toString(),
      statusLabel: (map['status_label'] ?? '').toString(),
      progress: ApiClient._toInt(map['progress']),
      canPay: ApiClient._toBool(map['can_pay']),
      customerAction: (map['customer_action'] ?? '').toString(),
      importeEstimado: ApiClient._toDoubleNullable(map['importe_estimado']),
      subtotal: ApiClient._toDoubleNullable(map['subtotal']),
      iva: ApiClient._toDoubleNullable(map['iva']),
      total: ApiClient._toDoubleNullable(map['total']),
      xmlCount: ApiClient._toInt(map['xml_count']),
      cfdiCount: ApiClient._toInt(map['cfdi_count']),
      dateFrom: (map['date_from'] ?? '').toString(),
      dateTo: (map['date_to'] ?? '').toString(),
      validUntil: (map['valid_until'] ?? '').toString(),
      priceSource: (map['price_source'] ?? '').toString(),
      discountCode: (map['discount_code'] ?? '').toString(),
      paidAt: (map['paid_at'] ?? '').toString(),
      createdAt: (map['created_at'] ?? '').toString(),
      updatedAt: (map['updated_at'] ?? '').toString(),
      transferReview: transfer,
      meta: ApiClient._asMap(map['meta']),
    );
  }

  String get amountLabel {
    final value = total ?? importeEstimado ?? subtotal ?? 0;
    return '\$${value.toStringAsFixed(2)} MXN';
  }

  String get periodLabel {
    if (dateFrom.trim().isEmpty && dateTo.trim().isEmpty) {
      return 'Periodo no definido';
    }
    if (dateFrom.trim().isNotEmpty && dateTo.trim().isNotEmpty) {
      return '$dateFrom al $dateTo';
    }
    return dateFrom.trim().isNotEmpty ? dateFrom : dateTo;
  }
}

class SatTimelineStep {
  final String key;
  final String title;
  final String subtitle;
  final bool done;
  final bool current;
  final bool pending;

  const SatTimelineStep({
    required this.key,
    required this.title,
    required this.subtitle,
    required this.done,
    required this.current,
    required this.pending,
  });
}

class SplashGate extends StatefulWidget {
  const SplashGate({super.key});

  @override
  State<SplashGate> createState() => _SplashGateState();
}

class _SplashGateState extends State<SplashGate> {
  String _status = 'Validando sesión...';

  @override
  void initState() {
    super.initState();
    unawaited(_bootstrap());
  }

  Future<void> _bootstrap() async {
    try {
      setState(() {
        _status = 'Conectando con la API móvil...';
      });

      await ApiClient.ping();

      final token = await AppStorage.getToken();

      if (!mounted) return;

      if ((token ?? '').trim().isEmpty) {
        _goToLogin();
        return;
      }

      setState(() {
        _status = 'Recuperando sesión...';
      });

      await ApiClient.me(token!);

      if (!mounted) return;
      _goToDashboard();
    } catch (_) {
      if (!mounted) return;
      _goToLogin();
    }
  }

  void _goToLogin() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(builder: (_) => const LoginPage()),
    );
  }

  void _goToDashboard() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(builder: (_) => const DashboardPage()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF0F172A), Color(0xFF1E293B), Color(0xFF334155)],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(28),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.shield_rounded,
                        size: 56,
                        color: Color(0xFF0F172A),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'PACTOPIA360 Cliente',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _status,
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: const Color(0xFF475569),
                        ),
                      ),
                      const SizedBox(height: 24),
                      const CircularProgressIndicator(),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _loginController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  bool _obscurePassword = true;
  bool _loading = false;
  String _error = '';
  String _info = 'Ingresa con correo o RFC y tu contraseña del portal cliente.';

  @override
  void dispose() {
    _loginController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();

    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _loading = true;
      _error = '';
      _info = 'Iniciando sesión...';
    });

    final result = await ApiClient.login(
      login: _loginController.text.trim(),
      password: _passwordController.text,
      deviceName: 'mobile_cliente_flutter',
    );

    if (!mounted) return;

    if (!result.ok) {
      setState(() {
        _loading = false;
        _error = result.message;
        _info = 'Revisa tus datos e intenta nuevamente.';
      });
      return;
    }

    await AppStorage.saveSession(
      token: result.token,
      userName: result.userName,
      userEmail: result.userEmail,
    );

    if (!mounted) return;

    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(builder: (_) => const DashboardPage()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            final isWide = constraints.maxWidth >= 960;

            return Row(
              children: [
                if (isWide)
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.all(40),
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            Color(0xFF0F172A),
                            Color(0xFF1E293B),
                            Color(0xFF334155),
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Spacer(),
                          const Icon(
                            Icons.business_center_rounded,
                            color: Colors.white,
                            size: 74,
                          ),
                          const SizedBox(height: 20),
                          Text(
                            'PACTOPIA360 Cliente',
                            style: textTheme.displaySmall?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            'Acceso móvil para clientes, SAT, cotizaciones y seguimiento de descargas.',
                            style: textTheme.titleMedium?.copyWith(
                              color: const Color(0xFFE2E8F0),
                              height: 1.45,
                            ),
                          ),
                          const SizedBox(height: 28),
                          const _BenefitItem(
                            icon: Icons.lock_outline_rounded,
                            text: 'Autenticación segura con token',
                          ),
                          const _BenefitItem(
                            icon: Icons.receipt_long_outlined,
                            text: 'Cotizaciones SAT desde la app',
                          ),
                          const _BenefitItem(
                            icon: Icons.cloud_done_outlined,
                            text: 'Consulta rápida del dashboard móvil',
                          ),
                          const Spacer(),
                        ],
                      ),
                    ),
                  ),
                Expanded(
                  child: Center(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 430),
                        child: Card(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Form(
                              key: _formKey,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Text(
                                    'Iniciar sesión',
                                    style: textTheme.headlineSmall?.copyWith(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    _info,
                                    style: textTheme.bodyMedium?.copyWith(
                                      color: const Color(0xFF64748B),
                                    ),
                                  ),
                                  const SizedBox(height: 24),
                                  TextFormField(
                                    controller: _loginController,
                                    textInputAction: TextInputAction.next,
                                    decoration: const InputDecoration(
                                      labelText: 'Correo o RFC',
                                      prefixIcon: Icon(
                                        Icons.person_outline_rounded,
                                      ),
                                    ),
                                    validator: (value) {
                                      if ((value ?? '').trim().isEmpty) {
                                        return 'Ingresa tu correo o RFC.';
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 16),
                                  TextFormField(
                                    controller: _passwordController,
                                    obscureText: _obscurePassword,
                                    textInputAction: TextInputAction.done,
                                    onFieldSubmitted: (_) =>
                                        _loading ? null : _submit(),
                                    decoration: InputDecoration(
                                      labelText: 'Contraseña',
                                      prefixIcon: const Icon(
                                        Icons.lock_outline_rounded,
                                      ),
                                      suffixIcon: IconButton(
                                        onPressed: () {
                                          setState(() {
                                            _obscurePassword =
                                                !_obscurePassword;
                                          });
                                        },
                                        icon: Icon(
                                          _obscurePassword
                                              ? Icons.visibility_off_outlined
                                              : Icons.visibility_outlined,
                                        ),
                                      ),
                                    ),
                                    validator: (value) {
                                      if ((value ?? '').isEmpty) {
                                        return 'Ingresa tu contraseña.';
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 16),
                                  if (_error.isNotEmpty)
                                    Container(
                                      padding: const EdgeInsets.all(14),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFFEE2E2),
                                        borderRadius: BorderRadius.circular(14),
                                        border: Border.all(
                                          color: const Color(0xFFFCA5A5),
                                        ),
                                      ),
                                      child: Text(
                                        _error,
                                        style: const TextStyle(
                                          color: Color(0xFF991B1B),
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  if (_error.isNotEmpty)
                                    const SizedBox(height: 16),
                                  ElevatedButton.icon(
                                    onPressed: _loading ? null : _submit,
                                    icon: _loading
                                        ? const SizedBox(
                                            width: 18,
                                            height: 18,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                            ),
                                          )
                                        : const Icon(Icons.login_rounded),
                                    label: Text(
                                      _loading ? 'Entrando...' : 'Entrar',
                                    ),
                                  ),
                                  const SizedBox(height: 14),
                                  Text(
                                    'Base URL API: ${ApiConfig.baseUrl}',
                                    textAlign: TextAlign.center,
                                    style: textTheme.bodySmall?.copyWith(
                                      color: const Color(0xFF94A3B8),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  bool _loading = true;
  String _error = '';
  String _userName = '';
  String _userEmail = '';
  Map<String, dynamic> _dashboard = <String, dynamic>{};

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });

    try {
      final token = await AppStorage.getToken();
      final userName = await AppStorage.getUserName();
      final userEmail = await AppStorage.getUserEmail();

      if ((token ?? '').trim().isEmpty) {
        if (!mounted) return;
        _goToLogin();
        return;
      }

      final dashboard = await ApiClient.dashboard(token!);

      if (!mounted) return;

      setState(() {
        _userName = userName ?? '';
        _userEmail = userEmail ?? '';
        _dashboard = dashboard;
        _loading = false;
      });
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'No se pudo cargar el dashboard. ${e.message ?? ''}'.trim();
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'Ocurrió un error al cargar el dashboard.';
      });
    }
  }

  Future<void> _logout() async {
    final token = await AppStorage.getToken();

    if ((token ?? '').trim().isNotEmpty) {
      await ApiClient.logout(token!);
    }

    await AppStorage.clearSession();

    if (!mounted) return;
    _goToLogin();
  }

  void _goToLogin() {
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginPage()),
      (_) => false,
    );
  }

  void _goToQuotes({String? initialRfc}) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => QuotesPage(initialRfc: initialRfc),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final body = _loading
        ? const Center(child: CircularProgressIndicator())
        : _error.isNotEmpty
        ? Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.error_outline_rounded, size: 48),
                  const SizedBox(height: 12),
                  Text(_error, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: _load,
                    child: const Text('Reintentar'),
                  ),
                ],
              ),
            ),
          )
        : _DashboardContent(
            userName: _userName,
            userEmail: _userEmail,
            dashboard: _dashboard,
            onRefresh: _load,
            onLogout: _logout,
            onOpenQuotes: _goToQuotes,
          );

    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        titleSpacing: 16,
        title: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: const Color(0xFF0F172A),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(
                Icons.dashboard_customize_rounded,
                color: Colors.white,
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: const [
                Text(
                  'PACTOPIA360',
                  style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
                ),
                Text(
                  'Cliente móvil',
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 11.5,
                    color: Color(0xFF64748B),
                  ),
                ),
              ],
            ),
          ],
        ),
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 6),
            child: IconButton(
              onPressed: _load,
              tooltip: 'Actualizar',
              style: IconButton.styleFrom(
                backgroundColor: Colors.white.withOpacity(0.86),
                side: const BorderSide(color: Color(0xFFE2E8F0)),
              ),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ),
          Container(
            margin: const EdgeInsets.only(right: 16),
            child: IconButton(
              onPressed: _logout,
              tooltip: 'Cerrar sesión',
              style: IconButton.styleFrom(
                backgroundColor: Colors.white.withOpacity(0.86),
                side: const BorderSide(color: Color(0xFFE2E8F0)),
              ),
              icon: const Icon(Icons.logout_rounded),
            ),
          ),
        ],
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFF8FAFC), Color(0xFFF1F5F9), Color(0xFFEFF6FF)],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: SafeArea(top: false, child: body),
      ),
    );
  }
}

class QuotesPage extends StatefulWidget {
  final String? initialRfc;

  const QuotesPage({super.key, this.initialRfc});

  @override
  State<QuotesPage> createState() => _QuotesPageState();
}

class _QuotesPageState extends State<QuotesPage> {
  bool _loading = true;
  bool _loadingMore = false;
  String _error = '';
  String _selectedStatus = '';
  late final TextEditingController _rfcController;
  late final TextEditingController _searchController;

  List<MobileQuote> _quotes = <MobileQuote>[];
  int _currentPage = 1;
  int _lastPage = 1;
  bool _hasMore = false;
  int _total = 0;

  static const List<DropdownMenuItem<String>> _statusItems = [
    DropdownMenuItem(value: '', child: Text('Todos')),
    DropdownMenuItem(value: 'borrador', child: Text('Borrador')),
    DropdownMenuItem(value: 'en_proceso', child: Text('En proceso')),
    DropdownMenuItem(value: 'cotizada', child: Text('Cotizada')),
    DropdownMenuItem(
      value: 'en_revision_pago',
      child: Text('Pago en revisión'),
    ),
    DropdownMenuItem(value: 'pagada', child: Text('Pagada')),
    DropdownMenuItem(value: 'en_descarga', child: Text('En descarga')),
    DropdownMenuItem(value: 'completada', child: Text('Completada')),
    DropdownMenuItem(value: 'cancelada', child: Text('Cancelada')),
  ];

  static const List<MapEntry<String, String>> _quickStatusChips = [
    MapEntry('', 'Todas'),
    MapEntry('borrador', 'Borrador'),
    MapEntry('en_proceso', 'En proceso'),
    MapEntry('cotizada', 'Cotizadas'),
    MapEntry('en_revision_pago', 'Pago en revisión'),
    MapEntry('pagada', 'Pagadas'),
    MapEntry('en_descarga', 'En descarga'),
    MapEntry('completada', 'Completadas'),
    MapEntry('cancelada', 'Canceladas'),
  ];

  @override
  void initState() {
    super.initState();
    _rfcController = TextEditingController(text: widget.initialRfc ?? '');
    _searchController = TextEditingController();
    unawaited(_load(reset: true));
  }

  @override
  void dispose() {
    _rfcController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load({required bool reset}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = '';
      });
    } else {
      setState(() {
        _loadingMore = true;
      });
    }

    try {
      final token = await AppStorage.getToken();

      if ((token ?? '').trim().isEmpty) {
        if (!mounted) return;
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute<void>(builder: (_) => const LoginPage()),
          (_) => false,
        );
        return;
      }

      final targetPage = reset ? 1 : (_currentPage + 1);

      final response = await ApiClient.quotes(
        token!,
        rfc: _rfcController.text.trim(),
        status: _selectedStatus,
        page: targetPage,
        perPage: 20,
      );

      if (!mounted) return;

      setState(() {
        if (reset) {
          _quotes = response.items;
        } else {
          _quotes = [..._quotes, ...response.items];
        }

        _currentPage = response.currentPage;
        _lastPage = response.lastPage;
        _hasMore = response.hasMore;
        _total = response.total;
        _loading = false;
        _loadingMore = false;
      });
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _loadingMore = false;
        _error = 'No se pudieron cargar las cotizaciones. ${e.message ?? ''}'
            .trim();
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _loadingMore = false;
        _error = 'Ocurrió un error al cargar las cotizaciones.';
      });
    }
  }

  Future<void> _applyQuickStatus(String value) async {
    if (_selectedStatus == value) return;

    setState(() {
      _selectedStatus = value;
    });

    await _load(reset: true);
  }

  Future<void> _openDetail(MobileQuote quote) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    MobileQuote detail = quote;

    try {
      detail = await ApiClient.quoteDetail(token!, quote.id);
    } catch (_) {
      // Si falla el detalle remoto, usamos el dato del listado.
    }

    if (!mounted) return;

    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(builder: (_) => QuoteDetailPage(quote: detail)),
    );

    if (!mounted) return;

    if (changed == true) {
      await _load(reset: true);
    }
  }

  List<MobileQuote> _filteredQuotes() {
    final term = _searchController.text.trim().toLowerCase();
    if (term.isEmpty) return _quotes;

    return _quotes
        .where((q) {
          final folio = q.folio.toLowerCase();
          final concepto = q.concepto.toLowerCase();
          final rfc = q.rfc.toLowerCase();
          final razon = q.razonSocial.toLowerCase();

          return folio.contains(term) ||
              concepto.contains(term) ||
              rfc.contains(term) ||
              razon.contains(term);
        })
        .toList(growable: false);
  }

  @override
  Widget build(BuildContext context) {
    final visibleQuotes = _filteredQuotes();

    final body = _loading
        ? const Center(child: CircularProgressIndicator())
        : _error.isNotEmpty
        ? Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.error_outline_rounded, size: 48),
                  const SizedBox(height: 12),
                  Text(_error, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => _load(reset: true),
                    child: const Text('Reintentar'),
                  ),
                ],
              ),
            ),
          )
        : RefreshIndicator(
            onRefresh: () => _load(reset: true),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Cotizaciones SAT',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Consulta, filtra y revisa el estado de las cotizaciones generadas en tu cuenta.',
                          style: Theme.of(context).textTheme.bodyMedium
                              ?.copyWith(color: const Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 16),
                        TextField(
                          controller: _rfcController,
                          textCapitalization: TextCapitalization.characters,
                          decoration: const InputDecoration(
                            labelText: 'Filtrar por RFC',
                            prefixIcon: Icon(Icons.badge_outlined),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _searchController,
                          onChanged: (_) => setState(() {}),
                          decoration: const InputDecoration(
                            labelText:
                                'Buscar en resultados por folio, concepto o razón social',
                            prefixIcon: Icon(Icons.search_rounded),
                          ),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          'Filtros rápidos',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(
                                fontWeight: FontWeight.w800,
                                color: const Color(0xFF0F172A),
                              ),
                        ),
                        const SizedBox(height: 10),
                        SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: Row(
                            children: _quickStatusChips.map((chip) {
                              final selected = _selectedStatus == chip.key;
                              final color = selected
                                  ? satStatusColor(
                                      chip.key.isEmpty ? 'todos' : chip.key,
                                    )
                                  : const Color(0xFFCBD5E1);

                              return Padding(
                                padding: const EdgeInsets.only(right: 8),
                                child: FilterChip(
                                  selected: selected,
                                  showCheckmark: false,
                                  label: Text(chip.value),
                                  onSelected: (_) =>
                                      _applyQuickStatus(chip.key),
                                  selectedColor: color.withOpacity(0.14),
                                  backgroundColor: Colors.white,
                                  side: BorderSide(
                                    color: selected
                                        ? color.withOpacity(0.45)
                                        : const Color(0xFFE2E8F0),
                                  ),
                                  labelStyle: TextStyle(
                                    color: selected
                                        ? color
                                        : const Color(0xFF334155),
                                    fontWeight: FontWeight.w800,
                                  ),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                ),
                              );
                            }).toList(),
                          ),
                        ),
                        const SizedBox(height: 14),
                        DropdownButtonFormField<String>(
                          initialValue: _selectedStatus,
                          items: _statusItems,
                          onChanged: (value) {
                            setState(() {
                              _selectedStatus = value ?? '';
                            });
                          },
                          decoration: const InputDecoration(
                            labelText: 'Estado (filtro avanzado)',
                            prefixIcon: Icon(Icons.tune_rounded),
                          ),
                        ),
                        const SizedBox(height: 14),
                        Row(
                          children: [
                            Expanded(
                              child: ElevatedButton.icon(
                                onPressed: () => _load(reset: true),
                                icon: const Icon(Icons.filter_alt_outlined),
                                label: Text(
                                  _selectedStatus.isEmpty
                                      ? 'Aplicar filtros'
                                      : 'Filtrar: $_selectedStatus',
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            IconButton(
                              tooltip: 'Limpiar filtros',
                              onPressed: () async {
                                setState(() {
                                  _selectedStatus = '';
                                  _rfcController.clear();
                                  _searchController.clear();
                                });
                                await _load(reset: true);
                              },
                              icon: const Icon(Icons.cleaning_services_rounded),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: OutlinedButton.icon(
                                onPressed: () async {
                                  final createdQuote =
                                      await Navigator.of(
                                        context,
                                      ).push<MobileQuote>(
                                        MaterialPageRoute<MobileQuote>(
                                          builder: (_) =>
                                              const CreateSatQuotePage(),
                                        ),
                                      );

                                  if (!mounted) return;

                                  if (createdQuote != null) {
                                    await Navigator.of(context).push(
                                      MaterialPageRoute<void>(
                                        builder: (_) => QuoteDetailPage(
                                          quote: createdQuote,
                                        ),
                                      ),
                                    );

                                    if (!mounted) return;
                                    await _load(reset: true);
                                  }
                                },
                                icon: const Icon(Icons.add_rounded),
                                label: const Text('Nueva cotización'),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            _InfoChip(label: 'Total API', value: '$_total'),
                            _InfoChip(
                              label: 'Visibles',
                              value: '${visibleQuotes.length}',
                            ),
                            _InfoChip(
                              label: 'Página',
                              value: '$_currentPage / $_lastPage',
                            ),
                            _InfoChip(
                              label: 'Filtro',
                              value: _selectedStatus.isEmpty
                                  ? 'Todas'
                                  : _selectedStatus,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                if (visibleQuotes.isEmpty)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        children: [
                          const Icon(Icons.inbox_outlined, size: 44),
                          const SizedBox(height: 12),
                          Text(
                            'No hay cotizaciones para los filtros o búsqueda actual.',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodyLarge,
                          ),
                        ],
                      ),
                    ),
                  )
                else
                  ...visibleQuotes.map(
                    (quote) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _QuoteCard(
                        quote: quote,
                        onTap: () => _openDetail(quote),
                      ),
                    ),
                  ),
                if (visibleQuotes.isNotEmpty) const SizedBox(height: 8),
                if (_hasMore)
                  ElevatedButton.icon(
                    onPressed: _loadingMore ? null : () => _load(reset: false),
                    icon: _loadingMore
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.expand_more_rounded),
                    label: Text(_loadingMore ? 'Cargando...' : 'Cargar más'),
                  ),
              ],
            ),
          );

    return Scaffold(
      appBar: AppBar(title: const Text('Cotizaciones SAT')),
      body: body,
    );
  }
}

class CreateSatQuotePage extends StatefulWidget {
  const CreateSatQuotePage({super.key});

  @override
  State<CreateSatQuotePage> createState() => _CreateSatQuotePageState();
}

class _CreateSatQuotePageState extends State<CreateSatQuotePage> {
  final _formKey = GlobalKey<FormState>();
  final _rfcCtrl = TextEditingController();
  final _xmlCountCtrl = TextEditingController(text: '100');
  final _dateFromCtrl = TextEditingController();
  final _dateToCtrl = TextEditingController();
  final _conceptoCtrl = TextEditingController();
  final _discountCodeCtrl = TextEditingController();

  String _tipoSolicitud = 'emitidos';
  bool _includeIva = true;
  bool _saving = false;
  bool _calculating = false;
  SatQuickCalcResult? _calc;

  static const _tipoItems = <DropdownMenuItem<String>>[
    DropdownMenuItem(value: 'emitidos', child: Text('Emitidos')),
    DropdownMenuItem(value: 'recibidos', child: Text('Recibidos')),
    DropdownMenuItem(value: 'ambos', child: Text('Ambos')),
  ];

  @override
  void dispose() {
    _rfcCtrl.dispose();
    _xmlCountCtrl.dispose();
    _dateFromCtrl.dispose();
    _dateToCtrl.dispose();
    _conceptoCtrl.dispose();
    _discountCodeCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickDate(TextEditingController controller) async {
    final now = DateTime.now();
    final initial = _parseDate(controller.text) ?? now;

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(now.year - 3),
      lastDate: DateTime(now.year + 1),
    );

    if (picked == null) return;

    controller.text =
        '${picked.year.toString().padLeft(4, '0')}-'
        '${picked.month.toString().padLeft(2, '0')}-'
        '${picked.day.toString().padLeft(2, '0')}';
  }

  DateTime? _parseDate(String value) {
    try {
      if (value.trim().isEmpty) return null;
      return DateTime.parse(value.trim());
    } catch (_) {
      return null;
    }
  }

  Future<void> _calculate() async {
    if (!_formKey.currentState!.validate()) return;

    final dateFrom = _parseDate(_dateFromCtrl.text);
    final dateTo = _parseDate(_dateToCtrl.text);

    if (dateFrom == null || dateTo == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Las fechas no son válidas.')),
      );
      return;
    }

    if (dateFrom.isAfter(dateTo)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('La fecha inicial no puede ser mayor a la final.'),
        ),
      );
      return;
    }

    final xmlCount = int.tryParse(_xmlCountCtrl.text.trim());
    if (xmlCount == null || xmlCount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('La cantidad XML debe ser mayor a 0.')),
      );
      return;
    }

    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    setState(() {
      _calculating = true;
    });

    try {
      final calc = await ApiClient.quickCalc(
        token!,
        rfc: _rfcCtrl.text,
        tipoSolicitud: _tipoSolicitud,
        xmlCount: xmlCount,
        dateFrom: _dateFromCtrl.text,
        dateTo: _dateToCtrl.text,
        concepto: _conceptoCtrl.text,
        discountCode: _discountCodeCtrl.text,
        includeIva: _includeIva,
      );

      if (!mounted) return;

      setState(() {
        _calc = calc;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Estimado calculado correctamente.')),
      );
    } on DioException catch (e) {
      if (!mounted) return;

      final body = ApiClient._asMap(e.response?.data);
      final message = ApiClient._messageFromBody(
        body,
        fallback: 'No se pudo calcular la cotización.',
      );

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } catch (_) {
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo calcular la cotización.')),
      );
    } finally {
      if (mounted) {
        setState(() {
          _calculating = false;
        });
      }
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final dateFrom = _parseDate(_dateFromCtrl.text);
    final dateTo = _parseDate(_dateToCtrl.text);

    if (dateFrom == null || dateTo == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Las fechas no son válidas.')),
      );
      return;
    }

    if (dateFrom.isAfter(dateTo)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('La fecha inicial no puede ser mayor a la final.'),
        ),
      );
      return;
    }

    final xmlCount = int.tryParse(_xmlCountCtrl.text.trim());
    if (xmlCount == null || xmlCount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('La cantidad XML debe ser mayor a 0.')),
      );
      return;
    }

    if (_calc == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Primero calcula el estimado antes de crear la cotización.',
          ),
        ),
      );
      return;
    }

    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    setState(() {
      _saving = true;
    });

    try {
      final quote = await ApiClient.createQuote(
        token!,
        rfc: _rfcCtrl.text,
        tipoSolicitud: _tipoSolicitud,
        xmlCount: xmlCount,
        dateFrom: _dateFromCtrl.text,
        dateTo: _dateToCtrl.text,
        concepto: _conceptoCtrl.text,
        discountCode: _discountCodeCtrl.text,
        includeIva: _includeIva,
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            quote.folio.isEmpty
                ? 'Cotización creada correctamente.'
                : 'Cotización creada: ${quote.folio}',
          ),
        ),
      );

      Navigator.of(context).pop(quote);
    } on DioException catch (e) {
      if (!mounted) return;

      final body = ApiClient._asMap(e.response?.data);
      final message = ApiClient._messageFromBody(
        body,
        fallback: 'No se pudo crear la cotización SAT.',
      );

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } catch (_) {
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo crear la cotización SAT.')),
      );
    } finally {
      if (mounted) {
        setState(() {
          _saving = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Nueva cotización SAT')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Datos de la solicitud',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _rfcCtrl,
                      onChanged: (_) => setState(() => _calc = null),
                      textCapitalization: TextCapitalization.characters,
                      decoration: const InputDecoration(
                        labelText: 'RFC',
                        prefixIcon: Icon(Icons.badge_outlined),
                      ),
                      validator: (value) {
                        if ((value ?? '').trim().isEmpty) {
                          return 'Ingresa el RFC.';
                        }
                        if ((value ?? '').trim().length < 12) {
                          return 'RFC inválido.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      initialValue: _tipoSolicitud,
                      items: _tipoItems,
                      onChanged: (value) {
                        setState(() {
                          _tipoSolicitud = value ?? 'emitidos';
                          _calc = null;
                        });
                      },
                      decoration: const InputDecoration(
                        labelText: 'Tipo de solicitud',
                        prefixIcon: Icon(Icons.swap_horiz_rounded),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _xmlCountCtrl,
                      onChanged: (_) => setState(() => _calc = null),
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Cantidad XML',
                        prefixIcon: Icon(Icons.numbers_rounded),
                      ),
                      validator: (value) {
                        final number = int.tryParse((value ?? '').trim());
                        if (number == null || number <= 0) {
                          return 'Ingresa una cantidad válida.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            controller: _dateFromCtrl,
                            readOnly: true,
                            onTap: () async {
                              await _pickDate(_dateFromCtrl);
                              if (mounted) {
                                setState(() => _calc = null);
                              }
                            },
                            decoration: const InputDecoration(
                              labelText: 'Fecha inicial',
                              prefixIcon: Icon(Icons.calendar_today_rounded),
                            ),
                            validator: (value) {
                              if ((value ?? '').trim().isEmpty) {
                                return 'Requerida';
                              }
                              return null;
                            },
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: TextFormField(
                            controller: _dateToCtrl,
                            readOnly: true,
                            onTap: () async {
                              await _pickDate(_dateToCtrl);
                              if (mounted) {
                                setState(() => _calc = null);
                              }
                            },
                            decoration: const InputDecoration(
                              labelText: 'Fecha final',
                              prefixIcon: Icon(Icons.event_rounded),
                            ),
                            validator: (value) {
                              if ((value ?? '').trim().isEmpty) {
                                return 'Requerida';
                              }
                              return null;
                            },
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _conceptoCtrl,
                      onChanged: (_) => setState(() => _calc = null),
                      maxLines: 2,
                      decoration: const InputDecoration(
                        labelText: 'Notas de la solicitud',
                        prefixIcon: Icon(Icons.description_outlined),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _discountCodeCtrl,
                      onChanged: (_) => setState(() => _calc = null),
                      decoration: const InputDecoration(
                        labelText: 'Código descuento',
                        prefixIcon: Icon(Icons.local_offer_outlined),
                      ),
                    ),
                    const SizedBox(height: 12),
                    SwitchListTile(
                      value: _includeIva,
                      onChanged: _saving
                          ? null
                          : (value) {
                              setState(() {
                                _includeIva = value;
                                _calc = null;
                              });
                            },
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Incluir IVA'),
                      subtitle: const Text(
                        'Actívalo para cotizar con IVA incluido.',
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: (_saving || _calculating)
                                ? null
                                : _calculate,
                            icon: _calculating
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.calculate_rounded),
                            label: Text(
                              _calculating ? 'Calculando...' : 'Calcular',
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: (_saving || _calculating)
                                ? null
                                : _submit,
                            icon: _saving
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.add_task_rounded),
                            label: Text(
                              _saving ? 'Creando...' : 'Crear cotización',
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (_calc != null) ...[
                      const SizedBox(height: 18),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Estimado previo',
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                            const SizedBox(height: 12),
                            Wrap(
                              spacing: 10,
                              runSpacing: 10,
                              children: [
                                _InfoChip(
                                  label: 'XML',
                                  value: '${_calc!.xmlCount}',
                                ),
                                _InfoChip(
                                  label: 'Fuente',
                                  value: _calc!.priceSource.isEmpty
                                      ? 'N/D'
                                      : _calc!.priceSource,
                                ),
                                _InfoChip(
                                  label: 'IVA',
                                  value: _calc!.includeIva
                                      ? 'Incluido'
                                      : 'No incluido',
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            _KeyValueRow(
                              label: 'Subtotal',
                              value: _calc!.subtotalLabel,
                            ),
                            _KeyValueRow(label: 'IVA', value: _calc!.ivaLabel),
                            _KeyValueRow(
                              label: 'Total',
                              value: _calc!.totalLabel,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class QuoteDetailPage extends StatefulWidget {
  final MobileQuote quote;

  const QuoteDetailPage({super.key, required this.quote});

  @override
  State<QuoteDetailPage> createState() => _QuoteDetailPageState();
}

class _QuoteDetailPageState extends State<QuoteDetailPage> {
  late MobileQuote _quote;
  bool _refreshing = false;
  bool _didChange = false;

  @override
  void initState() {
    super.initState();
    _quote = widget.quote;
  }

  Future<void> _reloadQuote({bool silent = false}) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    if (!silent) {
      setState(() {
        _refreshing = true;
      });
    }

    try {
      final detail = await ApiClient.quoteDetail(token!, _quote.id);

      if (!mounted) return;

      setState(() {
        _quote = detail;
        _didChange = true;
      });
    } catch (_) {
      if (!mounted || silent) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo actualizar la cotización.')),
      );
    } finally {
      if (mounted && !silent) {
        setState(() {
          _refreshing = false;
        });
      }
    }
  }

  Future<void> _openCheckout() async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    try {
      final res = await ApiClient.quoteCheckout(token!, _quote.id);
      final data = ApiClient._asMap(res['data']);
      final url = (data['checkout_url'] ?? '').toString();

      if (!mounted) return;

      if (url.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No se pudo generar el checkout.')),
        );
        return;
      }

      await openExternalUrl(context, url);

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Volviendo del pago, actualizando estado de la cotización...',
          ),
        ),
      );

      await Future<void>.delayed(const Duration(seconds: 2));
      await _reloadQuote();
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo iniciar el pago SAT.')),
      );
    }
  }

  Future<void> _openTransferProof() async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => SatTransferProofPage(quote: _quote),
      ),
    );

    if (!mounted) return;
    await _reloadQuote();
  }

  List<SatTimelineStep> _buildTimelineSteps(MobileQuote quote) {
    final status = quote.statusUi.trim().toLowerCase();
    final transfer = quote.transferReview ?? <String, dynamic>{};
    final reviewStatus = (transfer['review_status'] ?? '')
        .toString()
        .toLowerCase();

    final isCancelled = status == 'cancelada';
    final isCompleted = status == 'completada';
    final isDownloading = status == 'en_descarga';
    final isPaid = status == 'pagada';
    final isQuoted = status == 'cotizada';
    final isPaymentReview = status == 'en_revision_pago';
    final isProcessing = status == 'en_proceso' || status == 'borrador';

    int currentIndex = 0;

    if (isProcessing) {
      currentIndex = 0;
    } else if (isQuoted) {
      currentIndex = 1;
    } else if (isPaymentReview) {
      currentIndex = 2;
    } else if (isPaid || isDownloading) {
      currentIndex = 3;
    } else if (isCompleted) {
      currentIndex = 4;
    }

    final cancelledSuffix = isCancelled ? ' • Cancelada' : '';
    final rejectedSuffix = reviewStatus == 'rejected' ? ' • Rechazada' : '';

    return [
      SatTimelineStep(
        key: 'solicitud',
        title: 'Solicitud creada',
        subtitle: isCancelled
            ? 'La solicitud fue detenida.$cancelledSuffix'
            : 'Tu solicitud SAT fue registrada correctamente.',
        done: currentIndex > 0 || isCompleted,
        current: currentIndex == 0 && !isCancelled,
        pending: false,
      ),
      SatTimelineStep(
        key: 'cotizacion',
        title: 'Cotización emitida',
        subtitle: isCancelled
            ? 'La cotización ya no seguirá avanzando.$cancelledSuffix'
            : 'El importe fue calculado y quedó listo para pago.',
        done: currentIndex > 1 || isPaid || isDownloading || isCompleted,
        current: currentIndex == 1 && !isCancelled,
        pending: currentIndex < 1 && !isCancelled,
      ),
      SatTimelineStep(
        key: 'revision_pago',
        title: 'Validación de pago',
        subtitle: isCancelled
            ? 'El pago no será validado.$cancelledSuffix'
            : reviewStatus == 'approved'
            ? 'El comprobante fue aprobado.'
            : reviewStatus == 'rejected'
            ? 'El comprobante fue rechazado.$rejectedSuffix'
            : 'Stripe o soporte están validando tu pago.',
        done: currentIndex > 2 || isPaid || isDownloading || isCompleted,
        current: currentIndex == 2 && !isCancelled,
        pending: currentIndex < 2 && !isCancelled,
      ),
      SatTimelineStep(
        key: 'descarga',
        title: 'Descarga en proceso',
        subtitle: isCancelled
            ? 'No se realizará descarga.$cancelledSuffix'
            : 'Tu solicitud ya está en preparación operativa.',
        done: currentIndex > 3 || isCompleted,
        current: currentIndex == 3 && !isCancelled,
        pending: currentIndex < 3 && !isCancelled,
      ),
      SatTimelineStep(
        key: 'entrega',
        title: 'Entrega completada',
        subtitle: isCancelled
            ? 'Proceso finalizado sin entrega.$cancelledSuffix'
            : 'La descarga SAT quedó completada para tu cuenta.',
        done: isCompleted,
        current: currentIndex == 4 && !isCancelled,
        pending: !isCompleted && !isCancelled,
      ),
    ];
  }

  String _nextActionText(MobileQuote quote) {
    final status = quote.statusUi.trim().toLowerCase();
    final transfer = quote.transferReview ?? <String, dynamic>{};
    final reviewStatus = (transfer['review_status'] ?? '')
        .toString()
        .toLowerCase();

    if (status == 'cancelada') {
      return 'Esta solicitud fue cancelada. Si lo necesitas, crea una nueva cotización.';
    }

    if (reviewStatus == 'rejected') {
      return 'Tu comprobante fue rechazado. Puedes corregir el pago y volver a subir uno nuevo.';
    }

    if (status == 'borrador' || status == 'en_proceso') {
      return 'Tu solicitud aún está en análisis. En cuanto quede cotizada podrás pagar.';
    }

    if (status == 'cotizada') {
      return quote.canPay
          ? 'Tu cotización ya está lista. El siguiente paso es pagar con Stripe o enviar comprobante.'
          : 'Tu cotización está emitida y pendiente del siguiente movimiento operativo.';
    }

    if (status == 'en_revision_pago') {
      return 'Tu pago está en revisión. El siguiente paso es esperar validación de soporte.';
    }

    if (status == 'pagada' || status == 'en_descarga') {
      return 'Tu pago fue validado. El siguiente paso es la preparación y entrega de la descarga SAT.';
    }

    if (status == 'completada') {
      return 'Tu proceso SAT terminó correctamente.';
    }

    return 'Sigue revisando el estado de tu solicitud desde esta pantalla.';
  }

  @override
  Widget build(BuildContext context) {
    final quote = _quote;
    final transfer = quote.transferReview ?? <String, dynamic>{};
    final timelineSteps = _buildTimelineSteps(quote);
    final nextActionText = _nextActionText(quote);

    return PopScope<bool>(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        Navigator.of(context).pop(_didChange);
      },
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            onPressed: () => Navigator.of(context).pop(_didChange),
            icon: const Icon(Icons.arrow_back_rounded),
          ),
          title: Text(quote.folio.isNotEmpty ? quote.folio : 'Detalle'),
          actions: [
            IconButton(
              onPressed: _refreshing ? null : () => _reloadQuote(),
              tooltip: 'Actualizar',
              icon: _refreshing
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
        body: RefreshIndicator(
          onRefresh: () => _reloadQuote(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        quote.folio.isNotEmpty ? quote.folio : 'Sin folio',
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        quote.concepto.isNotEmpty
                            ? quote.concepto
                            : 'Sin concepto',
                        style: Theme.of(context).textTheme.bodyLarge,
                      ),
                      const SizedBox(height: 14),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          _StatusBadge(
                            text: quote.statusLabel.isEmpty
                                ? quote.statusUi
                                : quote.statusLabel,
                            statusKey: quote.statusUi.isEmpty
                                ? quote.statusDb
                                : quote.statusUi,
                          ),
                          _InfoChip(
                            label: 'RFC',
                            value: quote.rfc.isEmpty ? 'N/D' : quote.rfc,
                          ),
                          _InfoChip(
                            label: 'Pago',
                            value: quote.canPay
                                ? 'Disponible'
                                : 'No disponible',
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(999),
                        child: LinearProgressIndicator(
                          value: quote.progress.clamp(0, 100) / 100,
                          minHeight: 10,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Progreso: ${quote.progress}%',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: const Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Ruta de avance SAT',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        nextActionText,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: const Color(0xFF64748B),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 18),
                      ...timelineSteps.asMap().entries.map((entry) {
                        final index = entry.key;
                        final step = entry.value;
                        final isLast = index == timelineSteps.length - 1;

                        Color dotColor;
                        IconData dotIcon;

                        if (step.done) {
                          dotColor = const Color(0xFF166534);
                          dotIcon = Icons.check_rounded;
                        } else if (step.current) {
                          dotColor = const Color(0xFF1D4ED8);
                          dotIcon = Icons.radio_button_checked_rounded;
                        } else {
                          dotColor = const Color(0xFF94A3B8);
                          dotIcon = Icons.radio_button_unchecked_rounded;
                        }

                        return Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Column(
                              children: [
                                Container(
                                  width: 34,
                                  height: 34,
                                  decoration: BoxDecoration(
                                    color: dotColor.withOpacity(0.12),
                                    shape: BoxShape.circle,
                                  ),
                                  child: Icon(
                                    dotIcon,
                                    color: dotColor,
                                    size: 20,
                                  ),
                                ),
                                if (!isLast)
                                  Container(
                                    width: 2,
                                    height: 42,
                                    margin: const EdgeInsets.symmetric(
                                      vertical: 6,
                                    ),
                                    color: step.done
                                        ? const Color(0xFFBBF7D0)
                                        : const Color(0xFFE2E8F0),
                                  ),
                              ],
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Padding(
                                padding: const EdgeInsets.only(
                                  top: 4,
                                  bottom: 12,
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      step.title,
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleMedium
                                          ?.copyWith(
                                            fontWeight: FontWeight.w800,
                                            color: step.current
                                                ? const Color(0xFF0F172A)
                                                : const Color(0xFF1E293B),
                                          ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      step.subtitle,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(
                                            color: const Color(0xFF64748B),
                                            height: 1.35,
                                          ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        );
                      }),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Información general',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 14),
                      _KeyValueRow(
                        label: 'Razón social',
                        value: quote.razonSocial.isEmpty
                            ? 'N/D'
                            : quote.razonSocial,
                      ),
                      _KeyValueRow(
                        label: 'Tipo',
                        value: quote.tipo.isEmpty ? 'N/D' : quote.tipo,
                      ),
                      _KeyValueRow(label: 'Periodo', value: quote.periodLabel),
                      _KeyValueRow(
                        label: 'XML estimados',
                        value: '${quote.xmlCount}',
                      ),
                      _KeyValueRow(label: 'CFDI', value: '${quote.cfdiCount}'),
                      _KeyValueRow(label: 'Importe', value: quote.amountLabel),
                      _KeyValueRow(
                        label: 'Subtotal',
                        value: quote.subtotal != null
                            ? '\$${quote.subtotal!.toStringAsFixed(2)} MXN'
                            : 'N/D',
                      ),
                      _KeyValueRow(
                        label: 'IVA',
                        value: quote.iva != null
                            ? '\$${quote.iva!.toStringAsFixed(2)} MXN'
                            : 'N/D',
                      ),
                      _KeyValueRow(
                        label: 'Total',
                        value: quote.total != null
                            ? '\$${quote.total!.toStringAsFixed(2)} MXN'
                            : quote.amountLabel,
                      ),
                      _KeyValueRow(
                        label: 'Válida hasta',
                        value: quote.validUntil.isEmpty
                            ? 'N/D'
                            : quote.validUntil,
                      ),
                      _KeyValueRow(
                        label: 'Fuente precio',
                        value: quote.priceSource.isEmpty
                            ? 'N/D'
                            : quote.priceSource,
                      ),
                      _KeyValueRow(
                        label: 'Descuento',
                        value: quote.discountCode.isEmpty
                            ? 'Sin código'
                            : quote.discountCode,
                      ),
                    ],
                  ),
                ),
              ),
              if (transfer.isNotEmpty) ...[
                const SizedBox(height: 14),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Revisión de transferencia',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 14),
                        _KeyValueRow(
                          label: 'Review status',
                          value: (transfer['review_status'] ?? 'N/D')
                              .toString(),
                        ),
                        _KeyValueRow(
                          label: 'Monto enviado',
                          value: (transfer['transfer_amount'] ?? 'N/D')
                              .toString(),
                        ),
                        _KeyValueRow(
                          label: 'Monto esperado',
                          value: (transfer['expected_amount'] ?? 'N/D')
                              .toString(),
                        ),
                        _KeyValueRow(
                          label: 'Fecha',
                          value: (transfer['transfer_date'] ?? 'N/D')
                              .toString(),
                        ),
                        _KeyValueRow(
                          label: 'Banco',
                          value: (transfer['payer_bank'] ?? 'N/D').toString(),
                        ),
                        _KeyValueRow(
                          label: 'Pagador',
                          value: (transfer['payer_name'] ?? 'N/D').toString(),
                        ),
                        _KeyValueRow(
                          label: 'Referencia',
                          value: (transfer['reference'] ?? 'N/D').toString(),
                        ),
                        _KeyValueRow(
                          label: 'Riesgo',
                          value: (transfer['risk_level'] ?? 'N/D').toString(),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Acciones',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 14),
                      ElevatedButton.icon(
                        onPressed: (_refreshing || !quote.canPay)
                            ? null
                            : _openCheckout,
                        icon: const Icon(Icons.credit_card_rounded),
                        label: Text(
                          _refreshing ? 'Actualizando...' : 'Pagar con Stripe',
                        ),
                      ),
                      const SizedBox(height: 10),
                      OutlinedButton.icon(
                        onPressed: _refreshing ? null : _openTransferProof,
                        icon: const Icon(Icons.upload_file_rounded),
                        label: const Text('Subir comprobante'),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class SatTransferProofPage extends StatefulWidget {
  final MobileQuote quote;

  const SatTransferProofPage({super.key, required this.quote});

  @override
  State<SatTransferProofPage> createState() => _SatTransferProofPageState();
}

class _SatTransferProofPageState extends State<SatTransferProofPage> {
  final _formKey = GlobalKey<FormState>();
  final _referenceCtrl = TextEditingController();
  final _amountCtrl = TextEditingController();
  final _dateCtrl = TextEditingController();
  final _payerNameCtrl = TextEditingController();
  final _payerBankCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  String _selectedFilePath = '';
  String _selectedFileName = '';
  int _selectedFileBytes = 0;
  bool _selectedFileIsImage = false;

  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _dateCtrl.text =
        '${now.year.toString().padLeft(4, '0')}-'
        '${now.month.toString().padLeft(2, '0')}-'
        '${now.day.toString().padLeft(2, '0')}';

    final total = widget.quote.total ?? widget.quote.importeEstimado ?? 0;
    if (total > 0) {
      _amountCtrl.text = total.toStringAsFixed(2);
    }
  }

  @override
  void dispose() {
    _referenceCtrl.dispose();
    _amountCtrl.dispose();
    _dateCtrl.dispose();
    _payerNameCtrl.dispose();
    _payerBankCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_selectedFilePath.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Selecciona un comprobante.')),
      );
      return;
    }

    final lowerName = _selectedFileName.toLowerCase();
    final validExtension =
        lowerName.endsWith('.pdf') ||
        lowerName.endsWith('.jpg') ||
        lowerName.endsWith('.jpeg') ||
        lowerName.endsWith('.png');

    if (!validExtension) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('El comprobante debe ser PDF, JPG o PNG.'),
        ),
      );
      return;
    }

    if (_selectedFileBytes > 10 * 1024 * 1024) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('El archivo no debe exceder 10 MB.')),
      );
      return;
    }

    if (!_formKey.currentState!.validate()) return;

    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    setState(() {
      _saving = true;
    });

    try {
      await ApiClient.quoteTransferProof(
        token!,
        widget.quote.id,
        reference: _referenceCtrl.text.trim(),
        transferDate: _dateCtrl.text.trim(),
        transferAmount: _amountCtrl.text.trim(),
        proofFilePath: _selectedFilePath,
        payerName: _payerNameCtrl.text.trim(),
        payerBank: _payerBankCtrl.text.trim(),
        notes: _notesCtrl.text.trim(),
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Comprobante enviado. Pago en revisión.')),
      );

      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo enviar el comprobante.')),
      );
    } finally {
      if (mounted) {
        setState(() {
          _saving = false;
        });
      }
    }
  }

  Future<void> _pickFile() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
        withData: false,
      );

      if (result == null || result.files.isEmpty) return;

      final file = result.files.first;
      final path = file.path ?? '';
      final name = file.name;
      final ext = name.split('.').last.toLowerCase();

      if (path.trim().isEmpty) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No se pudo leer el archivo seleccionado.'),
          ),
        );
        return;
      }

      setState(() {
        _selectedFilePath = path;
        _selectedFileName = name;
        _selectedFileBytes = file.size;
        _selectedFileIsImage = ['jpg', 'jpeg', 'png'].contains(ext);
      });
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo seleccionar el archivo.')),
      );
    }
  }

  String _fileSizeLabel(int bytes) {
    if (bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    double size = bytes.toDouble();
    int unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex++;
    }

    return '${size.toStringAsFixed(size >= 10 || unitIndex == 0 ? 0 : 1)} ${units[unitIndex]}';
  }

  void _removeSelectedFile() {
    setState(() {
      _selectedFilePath = '';
      _selectedFileName = '';
      _selectedFileBytes = 0;
      _selectedFileIsImage = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final transfer = widget.quote.transferReview ?? <String, dynamic>{};
    final expectedAmount =
        (transfer['expected_amount'] ?? widget.quote.total ?? 0).toString();

    return Scaffold(
      appBar: AppBar(title: const Text('Comprobante SAT')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Referencia y transferencia',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _referenceCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Referencia asignada',
                        prefixIcon: Icon(Icons.tag_rounded),
                      ),
                      validator: (value) {
                        if ((value ?? '').trim().isEmpty) {
                          return 'Ingresa la referencia.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _amountCtrl,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: InputDecoration(
                        labelText: 'Monto transferido',
                        helperText: 'Monto esperado: $expectedAmount',
                        prefixIcon: const Icon(Icons.attach_money_rounded),
                      ),
                      validator: (value) {
                        if ((value ?? '').trim().isEmpty) {
                          return 'Ingresa el monto.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _dateCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Fecha transferencia (YYYY-MM-DD)',
                        prefixIcon: Icon(Icons.calendar_month_rounded),
                      ),
                      validator: (value) {
                        if ((value ?? '').trim().isEmpty) {
                          return 'Ingresa la fecha.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _payerNameCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Nombre del pagador',
                        prefixIcon: Icon(Icons.person_rounded),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _payerBankCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Banco emisor',
                        prefixIcon: Icon(Icons.account_balance_rounded),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton.icon(
                                onPressed: _saving ? null : _pickFile,
                                icon: const Icon(Icons.attach_file_rounded),
                                label: Text(
                                  _selectedFileName.isEmpty
                                      ? 'Seleccionar comprobante'
                                      : 'Cambiar comprobante',
                                ),
                              ),
                            ),
                            if (_selectedFileName.isNotEmpty) ...[
                              const SizedBox(width: 10),
                              IconButton(
                                onPressed: _saving ? null : _removeSelectedFile,
                                tooltip: 'Quitar archivo',
                                icon: const Icon(Icons.delete_outline_rounded),
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 10),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: _selectedFileName.isEmpty
                              ? Text(
                                  'Selecciona PDF, JPG o PNG para validar antes de enviar.',
                                  style: Theme.of(context).textTheme.bodySmall
                                      ?.copyWith(
                                        color: const Color(0xFF64748B),
                                      ),
                                )
                              : Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Icon(
                                          _selectedFileIsImage
                                              ? Icons.image_rounded
                                              : Icons.picture_as_pdf_rounded,
                                          color: const Color(0xFF0F172A),
                                        ),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Text(
                                            _selectedFileName,
                                            style: Theme.of(context)
                                                .textTheme
                                                .bodyMedium
                                                ?.copyWith(
                                                  fontWeight: FontWeight.w700,
                                                  color: const Color(
                                                    0xFF0F172A,
                                                  ),
                                                ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      'Tamaño: ${_fileSizeLabel(_selectedFileBytes)}',
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(
                                            color: const Color(0xFF64748B),
                                          ),
                                    ),
                                    if (_selectedFileIsImage) ...[
                                      const SizedBox(height: 12),
                                      ClipRRect(
                                        borderRadius: BorderRadius.circular(12),
                                        child: Image.file(
                                          File(_selectedFilePath),
                                          height: 180,
                                          width: double.infinity,
                                          fit: BoxFit.cover,
                                          errorBuilder: (_, _, _) {
                                            return Container(
                                              height: 120,
                                              alignment: Alignment.center,
                                              color: const Color(0xFFE2E8F0),
                                              child: const Text(
                                                'No se pudo previsualizar la imagen',
                                              ),
                                            );
                                          },
                                        ),
                                      ),
                                    ] else ...[
                                      const SizedBox(height: 10),
                                      Container(
                                        width: double.infinity,
                                        padding: const EdgeInsets.all(12),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFEFF6FF),
                                          borderRadius: BorderRadius.circular(
                                            12,
                                          ),
                                          border: Border.all(
                                            color: const Color(0xFFBFDBFE),
                                          ),
                                        ),
                                        child: const Text(
                                          'Archivo PDF listo para enviar.',
                                          style: TextStyle(
                                            color: Color(0xFF1D4ED8),
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _notesCtrl,
                      maxLines: 3,
                      decoration: const InputDecoration(
                        labelText: 'Notas',
                        prefixIcon: Icon(Icons.notes_rounded),
                      ),
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _saving ? null : _submit,
                        icon: _saving
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Icon(Icons.upload_rounded),
                        label: Text(
                          _saving ? 'Enviando...' : 'Enviar comprobante',
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DashboardContent extends StatelessWidget {
  final String userName;
  final String userEmail;
  final Map<String, dynamic> dashboard;
  final VoidCallback onRefresh;
  final VoidCallback onLogout;
  final void Function({String? initialRfc}) onOpenQuotes;

  const _DashboardContent({
    required this.userName,
    required this.userEmail,
    required this.dashboard,
    required this.onRefresh,
    required this.onLogout,
    required this.onOpenQuotes,
  });

  @override
  Widget build(BuildContext context) {
    final data = _map(dashboard['data']);
    final hero = _map(data['hero']);
    final health = _map(data['health']);

    final quickActions = _list(
      data['quick_actions'],
    ).map((item) => _map(item)).toList(growable: false);

    final modules = _list(
      data['modules'],
    ).map((item) => _map(item)).toList(growable: false);

    final account = _map(data['account']);
    final totals = _map(data['totals']);
    final vaultSummary = _map(data['vault_summary']);
    final storageBreakdown = _map(data['storage_breakdown']);

    final rfcs = _list(
      data['rfcs'],
    ).map((item) => _map(item)).toList(growable: false);

    final recentQuotes = _list(
      data['quotes'],
    ).map((item) => _map(item)).toList(growable: false);

    final recentFiles = _list(
      data['recent_files'],
    ).map((item) => _map(item)).toList(growable: false);

    final downloadSources = _list(
      data['download_sources'],
    ).map((item) => _map(item)).toList(growable: false);

    bool isModuleVisible(Map<String, dynamic> module) {
      final state = (module['state'] ?? 'active')
          .toString()
          .trim()
          .toLowerCase();
      final access = module['access'] == true;

      if (state == 'hidden') return false;
      if (module['hidden'] == true) return false;

      if (module['visible'] is bool && module['visible'] == false) {
        return false;
      }

      if (module['enabled'] is bool && module['enabled'] == false && !access) {
        return false;
      }

      return true;
    }

    const featuredKeys = {
      'sat_descargas',
      'facturacion',
      'crm',
      'inventario',
      'ventas',
      'reportes',
      'recursos_humanos',
      'timbres_hits',
    };

    final visibleModules = modules
        .where((m) => isModuleVisible(m))
        .toList(growable: false);

    final featuredModules = visibleModules
        .where((m) => featuredKeys.contains((m['key'] ?? '').toString()))
        .toList(growable: false);

    final otherModules = visibleModules
        .where((m) => !featuredKeys.contains((m['key'] ?? '').toString()))
        .toList(growable: false);

    final heroTitle = (hero['title'] ?? '').toString().trim().isNotEmpty
        ? (hero['title'] ?? '').toString().trim()
        : (userName.trim().isNotEmpty ? userName.trim() : 'PACTOPIA360');

    final heroSubtitle = (hero['subtitle'] ?? '').toString().trim();
    final heroPlan = (hero['plan'] ?? 'FREE').toString().trim().toUpperCase();
    final heroStatus = (hero['status'] ?? 'activa').toString().trim();
    final nextPayment = (hero['next_payment'] ?? '').toString().trim();

    final healthStatus = (health['status'] ?? 'ok').toString().trim();
    final healthMessage = (health['message'] ?? 'Cuenta operando correctamente')
        .toString()
        .trim();

    final activeModules = modules
        .where((m) => (m['state'] ?? 'active').toString() == 'active')
        .toList(growable: false);

    final blockedModules = modules
        .where((m) => (m['state'] ?? '').toString() == 'blocked')
        .length;

    final accountName = (account['nombre_comercial'] ?? '').toString().trim();
    final accountRfc = (account['rfc_padre'] ?? '').toString().trim();
    final totalQuotesLabel = recentQuotes.length.toString();
    final totalRfcsLabel = rfcs.length.toString();
    final recentFilesLabel = recentFiles.length.toString();
    final downloadSourcesLabel = downloadSources.length.toString();
    final vaultUsedLabel =
        (vaultSummary['used_human'] ??
                vaultSummary['used_label'] ??
                storageBreakdown['used_human'] ??
                storageBreakdown['used_label'] ??
                'N/D')
            .toString();
    final vaultAvailableLabel =
        (vaultSummary['available_human'] ??
                vaultSummary['available_label'] ??
                storageBreakdown['available_human'] ??
                storageBreakdown['available_label'] ??
                'N/D')
            .toString();
    final totalXmlLabel =
        (totals['xml_count'] ?? totals['xmls'] ?? totals['total_xml'] ?? '0')
            .toString();

    return RefreshIndicator(
      onRefresh: () async => onRefresh(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          _MobileHeroCard(
            title: heroTitle,
            subtitle: heroSubtitle,
            email: userEmail,
            plan: heroPlan,
            status: heroStatus,
            nextPayment: nextPayment,
            activeModules: activeModules.length,
            blockedModules: blockedModules,
          ),
          const SizedBox(height: 16),
          _AccountHealthCard(status: healthStatus, message: healthMessage),
          const SizedBox(height: 18),

          const _SectionTitle(
            title: 'Resumen general',
            subtitle: 'Tu operación principal y tus indicadores clave.',
          ),
          const SizedBox(height: 12),
          GridView.count(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisCount: MediaQuery.of(context).size.width >= 900 ? 4 : 2,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.18,
            children: [
              _StatCard(
                title: 'Módulos activos',
                value: '${activeModules.length}',
                icon: Icons.apps_rounded,
              ),
              _StatCard(
                title: 'Bloqueados',
                value: '$blockedModules',
                icon: Icons.lock_outline_rounded,
              ),
              _StatCard(
                title: 'RFCs',
                value: totalRfcsLabel,
                icon: Icons.badge_rounded,
              ),
              _StatCard(
                title: 'Cotizaciones',
                value: totalQuotesLabel,
                icon: Icons.receipt_long_rounded,
              ),
            ],
          ),

          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Visión general',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      if (accountName.isNotEmpty)
                        _InfoChip(label: 'Cuenta', value: accountName),
                      if (accountRfc.isNotEmpty)
                        _InfoChip(label: 'RFC principal', value: accountRfc),
                      _InfoChip(
                        label: 'Archivos recientes',
                        value: recentFilesLabel,
                      ),
                      _InfoChip(
                        label: 'Fuentes SAT',
                        value: downloadSourcesLabel,
                      ),
                      _InfoChip(label: 'XML', value: totalXmlLabel),
                      _InfoChip(label: 'Usado', value: vaultUsedLabel),
                      _InfoChip(
                        label: 'Disponible',
                        value: vaultAvailableLabel,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 18),
          const _SectionTitle(
            title: 'Acciones rápidas',
            subtitle: 'Lo más importante, al alcance de un toque.',
          ),
          const SizedBox(height: 12),
          GridView.builder(
            itemCount: quickActions.length,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: MediaQuery.of(context).size.width >= 900 ? 4 : 2,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.25,
            ),
            itemBuilder: (context, index) {
              final action = quickActions[index];
              return _QuickActionCard(
                label: (action['label'] ?? 'Acción').toString(),
                icon: _iconFromKey((action['icon'] ?? '').toString()),
                onTap: () => _handleQuickAction(
                  context,
                  key: (action['key'] ?? '').toString(),
                ),
              );
            },
          ),

          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 46,
                        height: 46,
                        decoration: BoxDecoration(
                          color: const Color(0xFFEFF6FF),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(
                          Icons.cloud_download_rounded,
                          color: Color(0xFF1D4ED8),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'SAT Descargas',
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Cotizaciones, pagos y seguimiento operativo.',
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(color: const Color(0xFF64748B)),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      _InfoChip(label: 'Cotizaciones', value: totalQuotesLabel),
                      _InfoChip(label: 'RFCs', value: totalRfcsLabel),
                      _InfoChip(label: 'Fuentes', value: downloadSourcesLabel),
                    ],
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: () => onOpenQuotes(),
                      icon: const Icon(Icons.arrow_forward_rounded),
                      label: const Text('Entrar a SAT'),
                    ),
                  ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 18),
          const _SectionTitle(
            title: 'Módulos estrella',
            subtitle:
                'Las herramientas más potentes de tu ecosistema, primero.',
          ),
          const SizedBox(height: 12),
          GridView.builder(
            itemCount: featuredModules.length,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: MediaQuery.of(context).size.width >= 1100 ? 4 : 2,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.02,
            ),
            itemBuilder: (context, index) {
              final module = featuredModules[index];
              final state = (module['state'] ?? 'active').toString();
              final access = module['access'] == true;
              return _ModuleCard(
                title: (module['name'] ?? 'Módulo').toString(),
                icon: _iconFromKey((module['icon'] ?? '').toString()),
                state: state,
                enabled: access && state == 'active',
                onTap: () => _handleModuleTap(context, module: module),
              );
            },
          ),

          const SizedBox(height: 18),
          const _SectionTitle(
            title: 'Todos los módulos',
            subtitle: 'Explora todo lo que tu cuenta tiene disponible.',
          ),
          const SizedBox(height: 12),
          GridView.builder(
            itemCount: otherModules.length,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: MediaQuery.of(context).size.width >= 1100 ? 4 : 2,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.02,
            ),
            itemBuilder: (context, index) {
              final module = otherModules[index];
              final state = (module['state'] ?? 'active').toString();
              final access = module['access'] == true;
              return _ModuleCard(
                title: (module['name'] ?? 'Módulo').toString(),
                icon: _iconFromKey((module['icon'] ?? '').toString()),
                state: state,
                enabled: access && state == 'active',
                onTap: () => _handleModuleTap(context, module: module),
              );
            },
          ),

          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Centro de control',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      _InfoChip(label: 'Plan', value: heroPlan),
                      _InfoChip(label: 'Estado', value: heroStatus),
                      _InfoChip(
                        label: 'Módulos activos',
                        value: '${activeModules.length}',
                      ),
                      _InfoChip(label: 'Bloqueados', value: '$blockedModules'),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'PACTOPIA360 móvil ahora arranca como plataforma general. SAT queda integrado como módulo principal dentro del ecosistema.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: const Color(0xFF475569),
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: onRefresh,
                          icon: const Icon(Icons.refresh_rounded),
                          label: const Text('Actualizar'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: onLogout,
                          icon: const Icon(Icons.logout_rounded),
                          label: const Text('Salir'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _handleQuickAction(
    BuildContext context, {
    required String key,
  }) async {
    switch (key) {
      case 'sat':
        onOpenQuotes();
        return;
      case 'pay':
        await _openBillingStatement(context);
        return;
      case 'account':
        await _openProfile(context);
        return;
      case 'invoices':
        await _openInvoices(context);
        return;
      case 'profile':
        await _openProfile(context);
        return;
      default:
        await _openGenericModule(
          context,
          module: {
            'key': key,
            'name': 'Acción rápida',
            'icon': 'apps',
            'summary': '',
            'headline': '',
            'chips': const <String>[],
            'kpis': const <Map<String, dynamic>>[],
          },
        );
        return;
    }
  }

  Future<void> _handleModuleTap(
    BuildContext context, {
    required Map<String, dynamic> module,
  }) async {
    final normalizedKey = (module['key'] ?? '').toString().trim().toLowerCase();
    final title = (module['name'] ?? 'Módulo').toString();
    final normalizedState = (module['state'] ?? 'active')
        .toString()
        .trim()
        .toLowerCase();
    final access = module['access'] == true;

    if (!access || normalizedState == 'blocked') {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$title está bloqueado en tu cuenta.')),
      );
      return;
    }

    if (normalizedState == 'inactive' || normalizedState == 'disabled') {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$title está inactivo por ahora.')),
      );
      return;
    }

    if (normalizedState == 'hidden') {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$title no está visible para esta cuenta.')),
      );
      return;
    }

    switch (normalizedKey) {
      case 'sat_descargas':
      case 'boveda_fiscal':
        onOpenQuotes();
        return;

      case 'mi_cuenta':
        await _openProfile(context);
        return;

      case 'pagos':
        await _openPayments(context);
        return;

      case 'facturas':
        await _openInvoices(context);
        return;

      case 'estado_cuenta':
        await _openBillingStatement(context);
        return;

      default:
        await _openGenericModule(context, module: module);
        return;
    }
  }

  Future<void> _openProfile(BuildContext context) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    try {
      final response = await ApiClient.profile(token!);
      if (!context.mounted) return;

      Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => ProfilePage(profile: response)),
      );
    } catch (_) {
      if (!context.mounted) return;
      _showError(context, 'No se pudo cargar Mi cuenta.');
    }
  }

  Future<void> _openPayments(BuildContext context) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    try {
      final rows = await ApiClient.payments(token!);
      if (!context.mounted) return;

      Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => PaymentsPage(items: rows)),
      );
    } catch (_) {
      if (!context.mounted) return;
      _showError(context, 'No se pudieron cargar los pagos.');
    }
  }

  Future<void> _openInvoices(BuildContext context) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    try {
      final rows = await ApiClient.invoices(token!);
      if (!context.mounted) return;

      Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => InvoicesPage(items: rows)),
      );
    } catch (_) {
      if (!context.mounted) return;
      _showError(context, 'No se pudieron cargar las facturas.');
    }
  }

  Future<void> _openBillingStatement(BuildContext context) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    try {
      final response = await ApiClient.billingStatement(token!);
      final data = _map(response['data']);
      final rows = _list(data['rows'])
          .map((item) => MobileBillingRow.fromMap(_map(item)))
          .toList(growable: false);

      if (!context.mounted) return;

      Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => BillingStatementPage(raw: response, rows: rows),
        ),
      );
    } catch (_) {
      if (!context.mounted) return;
      _showError(context, 'No se pudo cargar el estado de cuenta.');
    }
  }

  Future<void> _openGenericModule(
    BuildContext context, {
    required Map<String, dynamic> module,
  }) async {
    if (!context.mounted) return;

    final moduleKey = (module['key'] ?? '').toString();
    final title = (module['name'] ?? 'Módulo').toString();
    final description = (module['summary'] ?? '').toString();

    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ModuleWorkspacePage(
          title: title,
          moduleKey: moduleKey,
          description: description,
          accentIcon: _iconFromKey((module['icon'] ?? '').toString()),
          headline: (module['headline'] ?? '').toString(),
          chips: _list(module['chips']).map((e) => e.toString()).toList(),
          kpis: _list(
            module['kpis'],
          ).map((e) => _map(e)).toList(growable: false),
        ),
      ),
    );
  }

  void _showComingSoon(BuildContext context, String title) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          '$title aún está en ajuste, pero ya quedó integrado al flujo móvil.',
        ),
      ),
    );
  }

  void _showError(BuildContext context, String text) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  static Map<String, dynamic> _map(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) {
      return value.map((key, val) => MapEntry(key.toString(), val));
    }
    return <String, dynamic>{};
  }

  static List<dynamic> _list(dynamic value) {
    if (value is List) return value;
    return <dynamic>[];
  }

  static IconData _iconFromKey(String key) {
    switch (key.trim().toLowerCase()) {
      case 'receipt':
        return Icons.receipt_long_rounded;
      case 'cloud':
        return Icons.cloud_done_rounded;
      case 'storage':
        return Icons.storage_rounded;
      case 'people':
        return Icons.groups_rounded;
      case 'payments':
        return Icons.payments_rounded;
      case 'point_of_sale':
        return Icons.point_of_sale_rounded;
      case 'inventory':
        return Icons.inventory_2_rounded;
      case 'bar_chart':
        return Icons.bar_chart_rounded;
      case 'hub':
        return Icons.hub_rounded;
      case 'notifications':
        return Icons.notifications_active_rounded;
      case 'chat':
        return Icons.chat_bubble_rounded;
      case 'store':
        return Icons.storefront_rounded;
      case 'credit_card':
        return Icons.credit_card_rounded;
      case 'account_balance':
        return Icons.account_balance_wallet_rounded;
      case 'description':
        return Icons.description_rounded;
      case 'person':
        return Icons.person_rounded;

      case 'sat':
      case 'sat_descargas':
        return Icons.cloud_download_rounded;
      case 'boveda_fiscal':
        return Icons.folder_special_rounded;
      case 'facturacion':
        return Icons.receipt_long_rounded;
      case 'crm':
        return Icons.groups_rounded;
      case 'inventario':
        return Icons.inventory_2_rounded;
      case 'ventas':
        return Icons.point_of_sale_rounded;
      case 'reportes':
        return Icons.bar_chart_rounded;
      case 'recursos_humanos':
        return Icons.badge_rounded;
      case 'timbres_hits':
        return Icons.local_activity_rounded;
      case 'mi_cuenta':
        return Icons.person_rounded;
      case 'pagos':
        return Icons.payments_rounded;
      case 'facturas':
        return Icons.description_rounded;
      case 'estado_cuenta':
        return Icons.account_balance_wallet_rounded;

      default:
        return Icons.apps_rounded;
    }
  }
}

class _SectionTitle extends StatelessWidget {
  final String title;
  final String subtitle;

  const _SectionTitle({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 4),
        Text(
          subtitle,
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(color: const Color(0xFF64748B)),
        ),
      ],
    );
  }
}

class _MobileHeroCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final String email;
  final String plan;
  final String status;
  final String nextPayment;
  final int activeModules;
  final int blockedModules;

  const _MobileHeroCard({
    required this.title,
    required this.subtitle,
    required this.email,
    required this.plan,
    required this.status,
    required this.nextPayment,
    required this.activeModules,
    required this.blockedModules,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(26),
        gradient: const LinearGradient(
          colors: [Color(0xFF0F172A), Color(0xFF1E293B), Color(0xFF334155)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x220F172A),
            blurRadius: 24,
            offset: Offset(0, 12),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 54,
                  height: 54,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.14),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: Colors.white24),
                  ),
                  child: const Icon(
                    Icons.auto_awesome_rounded,
                    color: Colors.white,
                    size: 28,
                  ),
                ),
                const Spacer(),
                _HeroPill(label: plan),
              ],
            ),
            const SizedBox(height: 18),
            Text(
              title,
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w900,
              ),
            ),
            if (subtitle.trim().isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                subtitle,
                style: Theme.of(
                  context,
                ).textTheme.bodyLarge?.copyWith(color: const Color(0xFFE2E8F0)),
              ),
            ],
            if (email.trim().isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                email,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: const Color(0xFFCBD5E1),
                ),
              ),
            ],
            const SizedBox(height: 12),
            Text(
              'Tu empresa, tus módulos y tus operaciones críticas en una sola experiencia móvil.',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: const Color(0xFFE2E8F0),
                height: 1.45,
              ),
            ),
            const SizedBox(height: 18),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                _HeroPill(label: 'Estado: $status'),
                _HeroPill(
                  label: nextPayment.isNotEmpty
                      ? 'Próximo cobro: $nextPayment'
                      : 'Cobro sin fecha',
                ),
              ],
            ),
            const SizedBox(height: 18),
            Row(
              children: [
                Expanded(
                  child: _HeroMetric(
                    value: '$activeModules',
                    label: 'Módulos activos',
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _HeroMetric(
                    value: '$blockedModules',
                    label: 'Bloqueados',
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _HeroMetric extends StatelessWidget {
  final String value;
  final String label;

  const _HeroMetric({required this.value, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.12),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: const Color(0xFFE2E8F0)),
          ),
        ],
      ),
    );
  }
}

class _HeroPill extends StatelessWidget {
  final String label;

  const _HeroPill({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white24),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _AccountHealthCard extends StatelessWidget {
  final String status;
  final String message;

  const _AccountHealthCard({required this.status, required this.message});

  @override
  Widget build(BuildContext context) {
    final isOk = status.toLowerCase() == 'ok';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isOk ? const Color(0xFFECFDF5) : const Color(0xFFFEF2F2),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isOk ? const Color(0xFFA7F3D0) : const Color(0xFFFECACA),
        ),
      ),
      child: Row(
        children: [
          Icon(
            isOk ? Icons.verified_rounded : Icons.warning_amber_rounded,
            color: isOk ? const Color(0xFF047857) : const Color(0xFFB91C1C),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                fontWeight: FontWeight.w700,
                color: isOk ? const Color(0xFF065F46) : const Color(0xFF991B1B),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _QuickActionCard extends StatelessWidget {
  final String label;
  final IconData icon;
  final VoidCallback onTap;

  const _QuickActionCard({
    required this.label,
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF0F172A), Color(0xFF334155)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(icon, color: Colors.white),
              ),
              const Spacer(),
              Text(
                label,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  Text(
                    'Abrir ahora',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: const Color(0xFF64748B),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const Spacer(),
                  const Icon(
                    Icons.arrow_forward_rounded,
                    size: 18,
                    color: Color(0xFF64748B),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class ProfilePage extends StatelessWidget {
  final Map<String, dynamic> profile;

  const ProfilePage({super.key, required this.profile});

  @override
  Widget build(BuildContext context) {
    final data = ApiClient._asMap(profile['data']);
    final user = ApiClient._asMap(data['user']);
    final account = ApiClient._asMap(data['account']);

    return Scaffold(
      appBar: AppBar(title: const Text('Mi cuenta')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Perfil',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 14),
                  _KeyValueRow(
                    label: 'Nombre',
                    value: (user['nombre'] ?? 'N/D').toString(),
                  ),
                  _KeyValueRow(
                    label: 'Email',
                    value: (user['email'] ?? 'N/D').toString(),
                  ),
                  _KeyValueRow(
                    label: 'Cuenta',
                    value: (account['nombre_comercial'] ?? 'N/D').toString(),
                  ),
                  _KeyValueRow(
                    label: 'Razón social',
                    value: (account['razon_social'] ?? 'N/D').toString(),
                  ),
                  _KeyValueRow(
                    label: 'RFC',
                    value: (account['rfc_padre'] ?? 'N/D').toString(),
                  ),
                  _KeyValueRow(
                    label: 'Plan',
                    value: (account['plan'] ?? 'N/D').toString(),
                  ),
                  _KeyValueRow(
                    label: 'Estado',
                    value: (account['estado_cuenta'] ?? 'N/D').toString(),
                  ),
                  _KeyValueRow(
                    label: 'Modo cobro',
                    value: (account['modo_cobro'] ?? 'N/D').toString(),
                  ),
                  _KeyValueRow(
                    label: 'Próxima factura',
                    value: (account['next_invoice_date'] ?? 'N/D').toString(),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class PaymentsPage extends StatelessWidget {
  final List<MobilePaymentItem> items;

  const PaymentsPage({super.key, required this.items});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pagos')),
      body: items.isEmpty
          ? const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text('No hay pagos registrados por ahora.'),
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final item = items[index];
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.concept,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            _InfoChip(label: 'Monto', value: item.amountLabel),
                            _InfoChip(
                              label: 'Estado',
                              value: item.status.isEmpty ? 'N/D' : item.status,
                            ),
                            _InfoChip(
                              label: 'Periodo',
                              value: item.period.isEmpty ? 'N/D' : item.period,
                            ),
                            _InfoChip(
                              label: 'Provider',
                              value: item.provider.isEmpty
                                  ? 'N/D'
                                  : item.provider,
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Text(
                          item.createdAt.isEmpty ? 'Sin fecha' : item.createdAt,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: const Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}

class InvoicesPage extends StatefulWidget {
  final List<MobileInvoiceItem> items;

  const InvoicesPage({super.key, required this.items});

  @override
  State<InvoicesPage> createState() => _InvoicesPageState();
}

class _InvoicesPageState extends State<InvoicesPage> {
  String _downloadingId = '';

  Future<void> _downloadInvoice(String id) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    setState(() {
      _downloadingId = id;
    });

    try {
      final response = await ApiClient.invoiceDownloadUrl(token!, id);
      final data = ApiClient._asMap(response['data']);
      final url = (data['url'] ?? '').toString();

      if (!mounted) return;

      if (url.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No se pudo generar la descarga.')),
        );
        return;
      }

      await openExternalUrl(context, url);
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo descargar la factura.')),
      );
    } finally {
      if (mounted) {
        setState(() {
          _downloadingId = '';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Facturas')),
      body: widget.items.isEmpty
          ? const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text('No hay solicitudes de factura registradas.'),
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: widget.items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final item = widget.items[index];
                final isBusy = _downloadingId == item.id;

                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.period.isEmpty
                              ? 'Factura'
                              : 'Factura ${item.period}',
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            _InfoChip(
                              label: 'Estado',
                              value: item.status.isEmpty ? 'N/D' : item.status,
                            ),
                            _InfoChip(
                              label: 'ZIP',
                              value: item.hasZip ? 'Disponible' : 'Pendiente',
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Text(
                          item.updatedAt.isNotEmpty
                              ? 'Actualizado: ${item.updatedAt}'
                              : (item.createdAt.isNotEmpty
                                    ? 'Creado: ${item.createdAt}'
                                    : 'Sin fecha'),
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: const Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 14),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: (!item.hasZip || isBusy)
                                ? null
                                : () => _downloadInvoice(item.id),
                            icon: isBusy
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.download_rounded),
                            label: Text(
                              isBusy
                                  ? 'Abriendo...'
                                  : (item.hasZip
                                        ? 'Descargar ZIP'
                                        : 'ZIP pendiente'),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}

class BillingStatementPage extends StatefulWidget {
  final Map<String, dynamic> raw;
  final List<MobileBillingRow> rows;

  const BillingStatementPage({
    super.key,
    required this.raw,
    required this.rows,
  });

  @override
  State<BillingStatementPage> createState() => _BillingStatementPageState();
}

class _BillingStatementPageState extends State<BillingStatementPage> {
  bool _busy = false;

  Future<void> _openPdf(String period) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    setState(() => _busy = true);

    try {
      final response = await ApiClient.billingPdfUrl(token!, period);
      final data = ApiClient._asMap(response['data']);
      final url = (data['url'] ?? '').toString();

      if (!mounted) return;

      if (url.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No se pudo generar la URL del PDF.')),
        );
        return;
      }

      await openExternalUrl(context, url);
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('No se pudo abrir el PDF.')));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openPay(String period) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    setState(() => _busy = true);

    try {
      final response = await ApiClient.billingPayUrl(token!, period);
      final data = ApiClient._asMap(response['data']);
      final url = (data['url'] ?? '').toString();

      if (!mounted) return;

      if (url.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No se pudo generar la URL de pago.')),
        );
        return;
      }

      await openExternalUrl(context, url);
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo preparar el pago.')),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _requestInvoice(String period) async {
    final token = await AppStorage.getToken();
    if ((token ?? '').trim().isEmpty) return;

    setState(() => _busy = true);

    try {
      final response = await ApiClient.billingRequestInvoice(token!, period);
      final data = ApiClient._asMap(response['data']);
      final message = (data['message'] ?? 'Solicitud de factura enviada.')
          .toString()
          .trim();

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            message.isEmpty ? 'Solicitud de factura enviada.' : message,
          ),
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo solicitar la factura.')),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _money(dynamic value) {
    final n = ApiClient._toDoubleNullable(value) ?? 0.0;
    return '\$${n.toStringAsFixed(2)} MXN';
  }

  Widget _summaryChip(String label, String value) {
    return _InfoChip(label: label, value: value.trim().isEmpty ? 'N/D' : value);
  }

  @override
  Widget build(BuildContext context) {
    final data = ApiClient._asMap(widget.raw['data']);
    final summary = ApiClient._asMap(data['summary']);

    final saldoPendiente = _money(
      summary['saldo_pendiente'] ?? data['saldo_pendiente'],
    );
    final mensualidad = _money(
      summary['mensualidad'] ?? data['mensualidad_admin'],
    );
    final annualTotal = _money(
      summary['annual_total'] ?? data['annual_total_mxn'],
    );
    final rowsCount = (summary['rows_count'] ?? widget.rows.length).toString();
    final pendingRows = (summary['pending_rows'] ?? '0').toString();
    final paidRows = (summary['paid_rows'] ?? '0').toString();
    final isAnnual = summary['is_annual'] == true || data['is_annual'] == true;

    return Scaffold(
      appBar: AppBar(title: const Text('Estado de cuenta')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Resumen de billing',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      _summaryChip(
                        'Último pagado',
                        (data['last_paid'] ?? 'N/D').toString(),
                      ),
                      _summaryChip(
                        'Permitido',
                        (data['pay_allowed'] ?? 'N/D').toString(),
                      ),
                      _summaryChip('RFC', (data['rfc'] ?? 'N/D').toString()),
                      _summaryChip(
                        'Alias',
                        (data['alias'] ?? 'N/D').toString(),
                      ),
                      _summaryChip('Filas', rowsCount),
                      _summaryChip('Pendientes', pendingRows),
                      _summaryChip('Pagadas', paidRows),
                      _summaryChip('Saldo pendiente', saldoPendiente),
                      _summaryChip('Mensualidad', mensualidad),
                      _summaryChip('Total anual', annualTotal),
                      _summaryChip('Modo', isAnnual ? 'Anual' : 'Mensual'),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          if (widget.rows.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text('No hay periodos disponibles para mostrar.'),
              ),
            )
          else
            ...widget.rows.map((row) {
              final invoiceStatus = row.invoiceRequestStatus.trim().isEmpty
                  ? 'Sin solicitud'
                  : row.invoiceRequestStatus.trim();

              final zipStatus = row.invoiceHasZip ? 'Disponible' : 'Pendiente';
              final canRequestInvoice = !row.invoiceHasZip;

              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          row.period.isEmpty ? 'Periodo' : row.period,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            _InfoChip(
                              label: 'Estado',
                              value: row.status.isEmpty ? 'N/D' : row.status,
                            ),
                            _InfoChip(label: 'Cargo', value: row.chargeLabel),
                            _InfoChip(label: 'Pagado', value: row.paidLabel),
                            _InfoChip(label: 'Saldo', value: row.saldoLabel),
                            _InfoChip(label: 'Factura', value: invoiceStatus),
                            _InfoChip(label: 'ZIP', value: zipStatus),
                          ],
                        ),
                        const SizedBox(height: 10),
                        if (row.periodRange.isNotEmpty)
                          Text(
                            row.periodRange,
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(color: const Color(0xFF64748B)),
                          ),
                        if (row.priceSource.isNotEmpty) ...[
                          const SizedBox(height: 4),
                          Text(
                            'Fuente precio: ${row.priceSource}',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(color: const Color(0xFF64748B)),
                          ),
                        ],
                        const SizedBox(height: 14),
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton.icon(
                                onPressed: _busy
                                    ? null
                                    : () => _openPdf(row.period),
                                icon: const Icon(Icons.picture_as_pdf_rounded),
                                label: const Text('PDF'),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: ElevatedButton.icon(
                                onPressed: (!_busy && row.canPay)
                                    ? () => _openPay(row.period)
                                    : null,
                                icon: const Icon(Icons.credit_card_rounded),
                                label: const Text('Pagar'),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        SizedBox(
                          width: double.infinity,
                          child: OutlinedButton.icon(
                            onPressed: (_busy || !canRequestInvoice)
                                ? null
                                : () => _requestInvoice(row.period),
                            icon: Icon(
                              row.invoiceHasZip
                                  ? Icons.download_done_rounded
                                  : Icons.receipt_long_rounded,
                            ),
                            label: Text(
                              row.invoiceHasZip
                                  ? 'Factura ya disponible'
                                  : 'Solicitar factura',
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }
}

class ModuleWorkspacePage extends StatelessWidget {
  final String title;
  final String moduleKey;
  final String description;
  final IconData accentIcon;
  final String headline;
  final List<String> chips;
  final List<Map<String, dynamic>> kpis;

  const ModuleWorkspacePage({
    super.key,
    required this.title,
    required this.moduleKey,
    required this.description,
    required this.accentIcon,
    this.headline = '',
    this.chips = const [],
    this.kpis = const [],
  });

  String _moduleLabel() {
    final clean = moduleKey.trim().toLowerCase();

    switch (clean) {
      case 'crm':
        return 'CRM';
      case 'inventario':
        return 'Inventario';
      case 'ventas':
        return 'Ventas';
      case 'reportes':
        return 'Reportes';
      case 'facturacion':
        return 'Facturación';
      case 'mi_cuenta':
        return 'Mi cuenta';
      case 'pagos':
        return 'Pagos';
      case 'facturas':
        return 'Facturas';
      case 'estado_cuenta':
        return 'Estado de cuenta';
      case 'sat_descargas':
        return 'SAT Descargas';
      case 'boveda_fiscal':
        return 'Bóveda Fiscal SAT';
      case 'recursos_humanos':
        return 'Recursos Humanos';
      case 'timbres_hits':
        return 'Timbres / Hits';
      default:
        return title;
    }
  }

  List<Map<String, String>> _fallbackKpis() {
    final clean = moduleKey.trim().toLowerCase();

    switch (clean) {
      case 'crm':
        return const [
          {'label': 'Clientes', 'value': '0'},
          {'label': 'Contactos', 'value': '0'},
          {'label': 'Oportunidades', 'value': '0'},
          {'label': 'Seguimientos', 'value': '0'},
        ];
      case 'inventario':
        return const [
          {'label': 'Productos', 'value': '0'},
          {'label': 'Stock', 'value': '0'},
          {'label': 'Movimientos', 'value': '0'},
          {'label': 'Alertas', 'value': '0'},
        ];
      case 'ventas':
        return const [
          {'label': 'Ventas', 'value': '0'},
          {'label': 'Tickets', 'value': '0'},
          {'label': 'Facturables', 'value': '0'},
          {'label': 'Monto', 'value': '\$0'},
        ];
      case 'reportes':
        return const [
          {'label': 'Indicadores', 'value': '0'},
          {'label': 'Alertas', 'value': '0'},
          {'label': 'Cruces', 'value': '0'},
          {'label': 'Módulos', 'value': '7'},
        ];
      case 'facturacion':
        return const [
          {'label': 'CFDI', 'value': '0'},
          {'label': 'Borradores', 'value': '0'},
          {'label': 'Receptores', 'value': '0'},
          {'label': 'Hits', 'value': '0'},
        ];
      case 'recursos_humanos':
        return const [
          {'label': 'Empleados', 'value': '0'},
          {'label': 'Nóminas', 'value': '0'},
          {'label': 'CFDI nómina', 'value': '0'},
          {'label': 'Hits', 'value': '0'},
        ];
      case 'timbres_hits':
        return const [
          {'label': 'Saldo', 'value': '0'},
          {'label': 'Consumo', 'value': '0'},
          {'label': 'Compras', 'value': '0'},
          {'label': 'Alertas', 'value': '0'},
        ];
      case 'sat_descargas':
        return const [
          {'label': 'RFCs', 'value': '0'},
          {'label': 'Cotizaciones', 'value': '0'},
          {'label': 'Fuentes', 'value': '0'},
          {'label': 'XML', 'value': '0'},
        ];
      default:
        return const [
          {'label': 'Estado', 'value': 'OK'},
          {'label': 'Vista', 'value': 'Lista'},
          {'label': 'Canal', 'value': 'Móvil'},
          {'label': 'Fase', 'value': 'Base'},
        ];
    }
  }

  List<String> _fallbackChips() {
    final clean = moduleKey.trim().toLowerCase();

    switch (clean) {
      case 'crm':
        return const [
          'Clientes',
          'Contactos',
          'Oportunidades',
          'Seguimiento',
          'Ventas',
          'IA',
        ];
      case 'inventario':
        return const [
          'Productos',
          'Stock',
          'Movimientos',
          'Ventas',
          'Facturación',
          'IA',
        ];
      case 'ventas':
        return const [
          'Tickets',
          'Código de venta',
          'Monto',
          'Facturación',
          'Autofactura',
          'IA',
        ];
      case 'reportes':
        return const [
          'KPIs',
          'Dashboards',
          'Comparativos',
          'Alertas',
          'Cruces',
          'IA',
        ];
      case 'facturacion':
        return const [
          'CFDI',
          'Nuevo CFDI',
          'Ventas',
          'Receptores',
          'Conceptos',
          'Timbres',
        ];
      case 'recursos_humanos':
        return const [
          'Empleados',
          'Incidencias',
          'Nómina',
          'CFDI nómina',
          'Finiquitos',
          'IA',
        ];
      case 'timbres_hits':
        return const [
          'Saldo',
          'Consumo',
          'Compra',
          'Cotización',
          'Facturotopia',
          'IA',
        ];
      case 'sat_descargas':
        return const [
          'RFC',
          'Cotizaciones',
          'Pagos',
          'Seguimiento',
          'Bóveda',
          'Descargas',
        ];
      default:
        return const ['Módulo', 'Integrado', 'Móvil'];
    }
  }

  String _fallbackHeadline() {
    final clean = moduleKey.trim().toLowerCase();

    switch (clean) {
      case 'crm':
        return 'CRM + Ventas + Facturación + IA';
      case 'inventario':
        return 'Inventario + Ventas + Facturación + IA';
      case 'ventas':
        return 'Ventas + Inventario + Facturación + Autofactura';
      case 'reportes':
        return 'Reportes + KPIs + IA + Visión global';
      case 'facturacion':
        return 'Facturación + CFDI + Ventas + Timbres';
      case 'recursos_humanos':
        return 'RH + Nómina + CFDI Nómina + IA';
      case 'timbres_hits':
        return 'Timbres / Hits + Facturotopia + IA';
      case 'sat_descargas':
        return 'SAT Descargas + Cotizaciones + Bóveda';
      default:
        return 'PACTOPIA360 móvil';
    }
  }

  @override
  Widget build(BuildContext context) {
    final label = _moduleLabel();
    final effectiveHeadline = headline.trim().isNotEmpty
        ? headline.trim()
        : _fallbackHeadline();
    final effectiveChips = chips.isNotEmpty ? chips : _fallbackChips();
    final effectiveKpis = kpis.isNotEmpty
        ? kpis
        : _fallbackKpis()
              .map((e) => {'label': e['label'], 'value': e['value']})
              .toList(growable: false);

    return Scaffold(
      appBar: AppBar(title: Text(label)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(24),
              gradient: const LinearGradient(
                colors: [
                  Color(0xFF0F172A),
                  Color(0xFF1E293B),
                  Color(0xFF334155),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Padding(
              padding: const EdgeInsets.all(22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 58,
                    height: 58,
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: Colors.white24),
                    ),
                    child: Icon(accentIcon, color: Colors.white, size: 30),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    label,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    effectiveHeadline,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: const Color(0xFFE2E8F0),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    description.trim().isNotEmpty
                        ? description.trim()
                        : 'Este módulo ya forma parte del ecosistema móvil Pactopia360 y quedó listo para crecer.',
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: const Color(0xFFE2E8F0),
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: effectiveChips
                        .map((chip) => _HeroPill(label: chip))
                        .toList(),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          GridView.builder(
            itemCount: effectiveKpis.length,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: MediaQuery.of(context).size.width >= 900 ? 4 : 2,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.15,
            ),
            itemBuilder: (context, index) {
              final kpi = effectiveKpis[index];
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        (kpi['label'] ?? '').toString(),
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: const Color(0xFF64748B),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        (kpi['value'] ?? '').toString(),
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(
                              fontWeight: FontWeight.w900,
                              color: const Color(0xFF0F172A),
                            ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
          const SizedBox(height: 14),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Resumen del módulo',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 14),
                  _KeyValueRow(label: 'Nombre', value: label),
                  _KeyValueRow(label: 'Clave interna', value: moduleKey),
                  _KeyValueRow(label: 'Canal', value: 'PACTOPIA360 móvil'),
                  _KeyValueRow(label: 'Estado', value: 'Integrado'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Siguiente nivel',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Ahora este módulo ya puede recibir contenido real desde el backend, así que la app deja de depender solo de textos fijos y queda lista para crecer con tablas, filtros, formularios y acciones reales.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: const Color(0xFF475569),
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.arrow_back_rounded),
                      label: const Text('Volver al dashboard'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ModuleCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final String state;
  final bool enabled;
  final VoidCallback onTap;

  const _ModuleCard({
    required this.title,
    required this.icon,
    required this.state,
    required this.enabled,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final normalizedState = state.trim().toLowerCase();

    Color badgeColor;
    String badgeText;

    switch (normalizedState) {
      case 'blocked':
        badgeColor = const Color(0xFFB91C1C);
        badgeText = 'Bloqueado';
        break;
      case 'inactive':
        badgeColor = const Color(0xFFB45309);
        badgeText = 'Inactivo';
        break;
      default:
        badgeColor = const Color(0xFF166534);
        badgeText = 'Activo';
        break;
    }

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: enabled
                          ? const Color(0xFFF1F5F9)
                          : const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Icon(
                      icon,
                      color: enabled
                          ? const Color(0xFF0F172A)
                          : const Color(0xFF94A3B8),
                    ),
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: badgeColor.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      badgeText,
                      style: TextStyle(
                        color: badgeColor,
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
              const Spacer(),
              Text(
                title,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 6),
              Text(
                enabled ? 'Disponible ahora' : 'Acceso restringido',
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B)),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  const Spacer(),
                  Icon(
                    enabled
                        ? Icons.arrow_forward_rounded
                        : Icons.lock_outline_rounded,
                    size: 18,
                    color: enabled
                        ? const Color(0xFF334155)
                        : const Color(0xFF94A3B8),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _QuoteCard extends StatelessWidget {
  final MobileQuote quote;
  final VoidCallback onTap;
  final bool compact;

  const _QuoteCard({required this.quote, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final progress = quote.progress.clamp(0, 100) / 100;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              quote.folio.isEmpty ? 'Sin folio' : quote.folio,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(
              quote.concepto.isEmpty ? 'Sin concepto' : quote.concepto,
              maxLines: compact ? 2 : 3,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 10,
              runSpacing: 8,
              children: [
                _StatusBadge(
                  text: quote.statusLabel.isEmpty
                      ? quote.statusUi
                      : quote.statusLabel,
                  statusKey: quote.statusUi.isEmpty
                      ? quote.statusDb
                      : quote.statusUi,
                ),
                _MiniBadge(
                  icon: Icons.attach_money_rounded,
                  text: quote.amountLabel,
                ),
                if (quote.rfc.isNotEmpty)
                  _MiniBadge(icon: Icons.badge_outlined, text: quote.rfc),
              ],
            ),
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(value: progress, minHeight: 8),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Text(
                  'Progreso ${quote.progress}%',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: const Color(0xFF64748B),
                  ),
                ),
                const Spacer(),
                if (quote.canPay)
                  const Text(
                    'Pago disponible',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF166534),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _BenefitItem extends StatelessWidget {
  final IconData icon;
  final String text;

  const _BenefitItem({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Icon(icon, color: Colors.white),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: Theme.of(
                context,
              ).textTheme.bodyLarge?.copyWith(color: const Color(0xFFE2E8F0)),
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final String label;
  final String value;

  const _InfoChip({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Chip(
      label: Text('$label: $value'),
      backgroundColor: const Color(0xFFE2E8F0),
      side: BorderSide.none,
      labelStyle: const TextStyle(
        fontWeight: FontWeight.w700,
        color: Color(0xFF0F172A),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String text;
  final String statusKey;

  const _StatusBadge({required this.text, required this.statusKey});

  @override
  Widget build(BuildContext context) {
    final color = satStatusColor(statusKey);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withOpacity(0.22)),
      ),
      child: Text(
        text.isEmpty ? 'Sin estado' : text,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w800,
          fontSize: 12.5,
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;

  const _StatCard({
    required this.title,
    required this.value,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: const Color(0xFF0F172A)),
            const Spacer(),
            Text(
              value,
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: const Color(0xFF64748B)),
            ),
          ],
        ),
      ),
    );
  }
}

class _KeyValueRow extends StatelessWidget {
  final String label;
  final String value;

  const _KeyValueRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: const Color(0xFF64748B)),
            ),
          ),
          const SizedBox(width: 12),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class _MiniBadge extends StatelessWidget {
  final IconData icon;
  final String text;

  const _MiniBadge({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: const Color(0xFF334155)),
          const SizedBox(width: 6),
          Text(
            text,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: Color(0xFF0F172A),
            ),
          ),
        ],
      ),
    );
  }
}
