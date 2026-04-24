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
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'PACTOPIA360 Cliente',
      theme: PactopiaTheme.light(),
      home: const SplashGate(),
    );
  }
}

class PactopiaTheme {
  PactopiaTheme._();

  static const Color navy = Color(0xFF07111F);
  static const Color navy2 = Color(0xFF0F2344);
  static const Color blue = Color(0xFF2563EB);
  static const Color cyan = Color(0xFF06B6D4);
  static const Color purple = Color(0xFF7C3AED);
  static const Color bg = Color(0xFFF3F7FB);
  static const Color card = Color(0xFFFFFFFF);
  static const Color border = Color(0xFFDDE7F3);
  static const Color text = Color(0xFF102033);
  static const Color muted = Color(0xFF64748B);

  static ThemeData light() {
    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      textTheme: GoogleFonts.interTextTheme(),
    );

    return base.copyWith(
      scaffoldBackgroundColor: bg,
      colorScheme: ColorScheme.fromSeed(
        seedColor: blue,
        brightness: Brightness.light,
        primary: blue,
        secondary: cyan,
        tertiary: purple,
        surface: card,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        foregroundColor: text,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: text,
          fontSize: 18,
          fontWeight: FontWeight.w900,
        ),
      ),
      cardTheme: CardThemeData(
        color: card,
        elevation: 0,
        shadowColor: const Color(0x2207111F),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(24),
          side: const BorderSide(color: border),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 16,
        ),
        labelStyle: const TextStyle(color: muted, fontWeight: FontWeight.w600),
        prefixIconColor: navy2,
        suffixIconColor: navy2,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: blue, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: Color(0xFFDC2626)),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          elevation: 0,
          backgroundColor: blue,
          foregroundColor: Colors.white,
          disabledBackgroundColor: const Color(0xFFE2E8F0),
          disabledForegroundColor: const Color(0xFF94A3B8),
          minimumSize: const Size.fromHeight(54),
          textStyle: const TextStyle(
            fontWeight: FontWeight.w900,
            letterSpacing: 0.1,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: navy2,
          side: const BorderSide(color: border),
          minimumSize: const Size.fromHeight(52),
          textStyle: const TextStyle(fontWeight: FontWeight.w800),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
        ),
      ),
      chipTheme: base.chipTheme.copyWith(
        backgroundColor: const Color(0xFFEAF2FF),
        side: BorderSide.none,
        labelStyle: const TextStyle(color: navy2, fontWeight: FontWeight.w800),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      ),
    );
  }
}

class PactopiaLogoMark extends StatelessWidget {
  final double size;
  final bool light;

  const PactopiaLogoMark({super.key, this.size = 72, this.light = false});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * 0.28),
        gradient: const LinearGradient(
          colors: [Color(0xFF2563EB), Color(0xFF06B6D4), Color(0xFF7C3AED)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x442563EB),
            blurRadius: 28,
            offset: Offset(0, 14),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -size * 0.18,
            top: -size * 0.18,
            child: Container(
              width: size * 0.55,
              height: size * 0.55,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.18),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Center(
            child: Text(
              'P360',
              style: GoogleFonts.inter(
                color: Colors.white,
                fontSize: size * 0.25,
                fontWeight: FontWeight.w900,
                letterSpacing: -1,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class PactopiaGradientButton extends StatelessWidget {
  final VoidCallback? onPressed;
  final Widget icon;
  final String label;

  const PactopiaGradientButton({
    super.key,
    required this.onPressed,
    required this.icon,
    required this.label,
  });

  @override
  Widget build(BuildContext context) {
    final disabled = onPressed == null;

    return Opacity(
      opacity: disabled ? 0.55 : 1,
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          height: 56,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            gradient: disabled
                ? const LinearGradient(
                    colors: [Color(0xFFE2E8F0), Color(0xFFCBD5E1)],
                  )
                : const LinearGradient(
                    colors: [
                      Color(0xFF2563EB),
                      Color(0xFF06B6D4),
                      Color(0xFF7C3AED),
                    ],
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                  ),
            boxShadow: disabled
                ? []
                : const [
                    BoxShadow(
                      color: Color(0x332563EB),
                      blurRadius: 22,
                      offset: Offset(0, 10),
                    ),
                  ],
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              icon,
              const SizedBox(width: 10),
              Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ),
      ),
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
                      const PactopiaLogoMark(size: 76),
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
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF07111F), Color(0xFF0F2344), Color(0xFF123B7A)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final isWide = constraints.maxWidth >= 960;

              return Stack(
                children: [
                  Positioned(
                    top: -90,
                    right: -90,
                    child: Container(
                      width: 230,
                      height: 230,
                      decoration: BoxDecoration(
                        color: const Color(0xFF06B6D4).withOpacity(0.22),
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: -110,
                    left: -90,
                    child: Container(
                      width: 260,
                      height: 260,
                      decoration: BoxDecoration(
                        color: const Color(0xFF7C3AED).withOpacity(0.20),
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
                  Row(
                    children: [
                      if (isWide)
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.all(44),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const PactopiaLogoMark(size: 82, light: true),
                                const Spacer(),
                                Text(
                                  'PACTOPIA360',
                                  style: textTheme.displaySmall?.copyWith(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: -1,
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  'Tu ERP, facturación, SAT, clientes y operación en una sola plataforma.',
                                  style: textTheme.titleMedium?.copyWith(
                                    color: const Color(0xFFE2E8F0),
                                    height: 1.45,
                                  ),
                                ),
                                const SizedBox(height: 30),
                                const _BenefitItem(
                                  icon: Icons.verified_user_rounded,
                                  text: 'Acceso seguro al portal cliente',
                                ),
                                const _BenefitItem(
                                  icon: Icons.receipt_long_rounded,
                                  text: 'Facturación y estado de cuenta',
                                ),
                                const _BenefitItem(
                                  icon: Icons.cloud_done_rounded,
                                  text:
                                      'SAT, cotizaciones y módulos conectados',
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
                              child: Container(
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.96),
                                  borderRadius: BorderRadius.circular(30),
                                  border: Border.all(
                                    color: Colors.white.withOpacity(0.55),
                                  ),
                                  boxShadow: const [
                                    BoxShadow(
                                      color: Color(0x33000000),
                                      blurRadius: 34,
                                      offset: Offset(0, 20),
                                    ),
                                  ],
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.all(26),
                                  child: Form(
                                    key: _formKey,
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.stretch,
                                      children: [
                                        Center(
                                          child: Column(
                                            children: [
                                              const PactopiaLogoMark(size: 76),
                                              const SizedBox(height: 18),
                                              Text(
                                                'Bienvenido',
                                                textAlign: TextAlign.center,
                                                style: textTheme.headlineMedium
                                                    ?.copyWith(
                                                      fontWeight:
                                                          FontWeight.w900,
                                                      color: PactopiaTheme.navy,
                                                      letterSpacing: -0.6,
                                                    ),
                                              ),
                                              const SizedBox(height: 6),
                                              Text(
                                                'Inicia sesión en tu cuenta cliente',
                                                textAlign: TextAlign.center,
                                                style: textTheme.bodyMedium
                                                    ?.copyWith(
                                                      color:
                                                          PactopiaTheme.muted,
                                                      fontWeight:
                                                          FontWeight.w600,
                                                    ),
                                              ),
                                            ],
                                          ),
                                        ),
                                        const SizedBox(height: 26),
                                        Container(
                                          padding: const EdgeInsets.all(14),
                                          decoration: BoxDecoration(
                                            color: const Color(0xFFEAF2FF),
                                            borderRadius: BorderRadius.circular(
                                              18,
                                            ),
                                            border: Border.all(
                                              color: const Color(0xFFCFE0FF),
                                            ),
                                          ),
                                          child: Row(
                                            children: [
                                              const Icon(
                                                Icons.info_outline_rounded,
                                                color: PactopiaTheme.blue,
                                                size: 20,
                                              ),
                                              const SizedBox(width: 10),
                                              Expanded(
                                                child: Text(
                                                  _info,
                                                  style: textTheme.bodySmall
                                                      ?.copyWith(
                                                        color:
                                                            PactopiaTheme.navy2,
                                                        height: 1.35,
                                                        fontWeight:
                                                            FontWeight.w700,
                                                      ),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                        const SizedBox(height: 22),
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
                                        const SizedBox(height: 14),
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
                                                    ? Icons
                                                          .visibility_off_outlined
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
                                              color: const Color(0xFFFFE4E6),
                                              borderRadius:
                                                  BorderRadius.circular(18),
                                              border: Border.all(
                                                color: const Color(0xFFFECDD3),
                                              ),
                                            ),
                                            child: Row(
                                              children: [
                                                const Icon(
                                                  Icons.error_outline_rounded,
                                                  color: Color(0xFFBE123C),
                                                ),
                                                const SizedBox(width: 10),
                                                Expanded(
                                                  child: Text(
                                                    _error,
                                                    style: const TextStyle(
                                                      color: Color(0xFF9F1239),
                                                      fontWeight:
                                                          FontWeight.w800,
                                                    ),
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                        if (_error.isNotEmpty)
                                          const SizedBox(height: 16),
                                        PactopiaGradientButton(
                                          onPressed: _loading ? null : _submit,
                                          icon: _loading
                                              ? const SizedBox(
                                                  width: 18,
                                                  height: 18,
                                                  child:
                                                      CircularProgressIndicator(
                                                        strokeWidth: 2,
                                                        color: Colors.white,
                                                      ),
                                                )
                                              : const Icon(
                                                  Icons.login_rounded,
                                                  color: Colors.white,
                                                ),
                                          label: _loading
                                              ? 'Entrando...'
                                              : 'Entrar',
                                        ),
                                        const SizedBox(height: 18),
                                        Container(
                                          padding: const EdgeInsets.symmetric(
                                            horizontal: 12,
                                            vertical: 10,
                                          ),
                                          decoration: BoxDecoration(
                                            color: const Color(0xFFF8FAFC),
                                            borderRadius: BorderRadius.circular(
                                              14,
                                            ),
                                            border: Border.all(
                                              color: const Color(0xFFE2E8F0),
                                            ),
                                          ),
                                          child: Text(
                                            'API: ${ApiConfig.baseUrl}',
                                            textAlign: TextAlign.center,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: textTheme.bodySmall
                                                ?.copyWith(
                                                  color: const Color(
                                                    0xFF94A3B8,
                                                  ),
                                                  fontWeight: FontWeight.w700,
                                                ),
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
                  ),
                ],
              );
            },
          ),
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
  int _tabIndex = 0;
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
        ? const _MobileLoadingState()
        : _error.isNotEmpty
        ? _MobileErrorState(error: _error, onRetry: _load)
        : _DashboardContent(
            userName: _userName,
            userEmail: _userEmail,
            dashboard: _dashboard,
            selectedTab: _tabIndex,
            onSelectTab: (index) => setState(() => _tabIndex = index),
            onRefresh: _load,
            onLogout: _logout,
            onOpenQuotes: _goToQuotes,
          );

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FB),
      body: body,
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tabIndex,
        height: 72,
        backgroundColor: Colors.white,
        indicatorColor: const Color(0xFFEAF2FF),
        elevation: 0,
        onDestinationSelected: (index) {
          setState(() {
            _tabIndex = index;
          });
        },
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home_rounded),
            label: 'Inicio',
          ),
          NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            selectedIcon: Icon(Icons.receipt_long_rounded),
            label: 'Facturación',
          ),
          NavigationDestination(
            icon: Icon(Icons.cloud_download_outlined),
            selectedIcon: Icon(Icons.cloud_download_rounded),
            label: 'SAT',
          ),
          NavigationDestination(
            icon: Icon(Icons.apps_outlined),
            selectedIcon: Icon(Icons.apps_rounded),
            label: 'Módulos',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Cuenta',
          ),
        ],
      ),
    );
  }
}

class _MobileLoadingState extends StatelessWidget {
  const _MobileLoadingState();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: Color(0xFFF3F7FB),
      body: Center(child: CircularProgressIndicator()),
    );
  }
}

class _MobileErrorState extends StatelessWidget {
  final String error;
  final VoidCallback onRetry;

  const _MobileErrorState({required this.error, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(26),
              border: Border.all(color: const Color(0xFFDDE7F3)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.error_outline_rounded,
                  size: 52,
                  color: Color(0xFFDC2626),
                ),
                const SizedBox(height: 14),
                Text(
                  error,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Color(0xFF102033),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 18),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: onRetry,
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Reintentar'),
                  ),
                ),
              ],
            ),
          ),
        ),
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
  final int selectedTab;
  final ValueChanged<int> onSelectTab;
  final VoidCallback onRefresh;
  final VoidCallback onLogout;
  final void Function({String? initialRfc}) onOpenQuotes;

  const _DashboardContent({
    required this.userName,
    required this.userEmail,
    required this.dashboard,
    required this.selectedTab,
    required this.onSelectTab,
    required this.onRefresh,
    required this.onLogout,
    required this.onOpenQuotes,
  });

  @override
  Widget build(BuildContext context) {
    final data = _map(dashboard['data']);
    final hero = _map(data['hero']);
    final health = _map(data['health']);
    final account = _map(data['account']);
    final totals = _map(data['totals']);
    final vaultSummary = _map(data['vault_summary']);
    final storageBreakdown = _map(data['storage_breakdown']);

    final quickActions = _list(
      data['quick_actions'],
    ).map((item) => _map(item)).toList(growable: false);

    final modules = _list(
      data['modules'],
    ).map((item) => _map(item)).toList(growable: false);

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

    final heroTitle = (hero['title'] ?? '').toString().trim().isNotEmpty
        ? (hero['title'] ?? '').toString().trim()
        : (userName.trim().isNotEmpty ? userName.trim() : 'PACTOPIA360');

    final heroPlan = (hero['plan'] ?? 'FREE').toString().trim().toUpperCase();
    final heroStatus = (hero['status'] ?? 'activa').toString().trim();
    final nextPayment = (hero['next_payment'] ?? '').toString().trim();

    final healthStatus = (health['status'] ?? 'ok').toString().trim();
    final healthMessage = (health['message'] ?? 'Cuenta operando correctamente')
        .toString()
        .trim();

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

    final visibleModules = modules
        .where(isModuleVisible)
        .toList(growable: false);

    final activeModules = visibleModules
        .where((m) => (m['state'] ?? 'active').toString() == 'active')
        .toList(growable: false);

    final blockedModules = visibleModules
        .where((m) => (m['state'] ?? '').toString() == 'blocked')
        .length;

    final accountName = (account['nombre_comercial'] ?? '').toString().trim();
    final accountRfc = (account['rfc_padre'] ?? '').toString().trim();

    final totalRfcsLabel = rfcs.length.toString();
    final totalQuotesLabel = recentQuotes.length.toString();
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

    final homeInsights = _map(data['home_insights']);

    final pages = [
      _MobileHomeTab(
        title: heroTitle,
        email: userEmail,
        plan: heroPlan,
        status: heroStatus,
        nextPayment: nextPayment,
        healthStatus: healthStatus,
        healthMessage: healthMessage,
        activeModules: activeModules.length,
        blockedModules: blockedModules,
        rfcs: totalRfcsLabel,
        quotes: totalQuotesLabel,
        xml: totalXmlLabel,
        files: recentFilesLabel,
        quickActions: quickActions,
        onRefresh: onRefresh,
        onLogout: onLogout,
        onOpenQuotes: onOpenQuotes,
        onOpenAction: (key) => _handleQuickAction(context, key: key),
        homeInsights: homeInsights,
      ),
      _MobileBillingHubTab(
        plan: heroPlan,
        status: heroStatus,
        accountName: accountName,
        accountRfc: accountRfc,
        onOpenStatement: () => _openBillingStatement(context),
        onOpenInvoices: () => _openInvoices(context),
        onOpenProfile: () => _openProfile(context),
      ),
      _MobileSatHubTab(
        quotes: totalQuotesLabel,
        rfcs: totalRfcsLabel,
        sources: downloadSourcesLabel,
        xml: totalXmlLabel,
        files: recentFilesLabel,
        vaultUsed: vaultUsedLabel,
        vaultAvailable: vaultAvailableLabel,
        onOpenQuotes: onOpenQuotes,
      ),
      _MobileModulesTab(
        modules: visibleModules,
        onOpenModule: (module) => _handleModuleTap(context, module: module),
      ),
      _MobileAccountTab(
        title: heroTitle,
        email: userEmail,
        plan: heroPlan,
        status: heroStatus,
        nextPayment: nextPayment,
        accountName: accountName,
        accountRfc: accountRfc,
        onRefresh: onRefresh,
        onLogout: onLogout,
        onOpenProfile: () => _openProfile(context),
        onOpenPayments: () => _openPayments(context),
        onOpenInvoices: () => _openInvoices(context),
        onOpenStatement: () => _openBillingStatement(context),
      ),
    ];

    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async => onRefresh(),
        child: pages[selectedTab],
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
      case 'facturacion':
        return Icons.receipt_long_rounded;
      case 'cloud':
      case 'sat':
      case 'sat_descargas':
        return Icons.cloud_download_rounded;
      case 'storage':
      case 'boveda_fiscal':
        return Icons.folder_special_rounded;
      case 'people':
      case 'crm':
        return Icons.groups_rounded;
      case 'payments':
      case 'pagos':
        return Icons.payments_rounded;
      case 'point_of_sale':
      case 'ventas':
        return Icons.point_of_sale_rounded;
      case 'inventory':
      case 'inventario':
        return Icons.inventory_2_rounded;
      case 'bar_chart':
      case 'reportes':
        return Icons.bar_chart_rounded;
      case 'recursos_humanos':
        return Icons.badge_rounded;
      case 'timbres_hits':
        return Icons.local_activity_rounded;
      case 'mi_cuenta':
      case 'person':
        return Icons.person_rounded;
      case 'facturas':
      case 'description':
        return Icons.description_rounded;
      case 'estado_cuenta':
      case 'account_balance':
        return Icons.account_balance_wallet_rounded;
      default:
        return Icons.apps_rounded;
    }
  }
}

class _TodayOverviewCard extends StatelessWidget {
  final String title;
  final String email;
  final String plan;
  final String status;
  final String amount;
  final String quotesToday;
  final String pending;
  final String completed;

  const _TodayOverviewCard({
    required this.title,
    required this.email,
    required this.plan,
    required this.status,
    required this.amount,
    required this.quotesToday,
    required this.pending,
    required this.completed,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        gradient: const LinearGradient(
          colors: [Color(0xFF06111F), Color(0xFF0F2344), Color(0xFF2563EB)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x332563EB),
            blurRadius: 28,
            offset: Offset(0, 14),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -44,
            top: -44,
            child: Container(
              width: 145,
              height: 145,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.10),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(
                    Icons.insights_rounded,
                    color: Colors.white,
                    size: 30,
                  ),
                  const Spacer(),
                  _GlassPill(text: plan),
                ],
              ),
              const SizedBox(height: 18),
              Text(
                'Hoy en Pactopia360',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 25,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.6,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                email.isEmpty ? title : email,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Color(0xFFE2E8F0),
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 18),
              Text(
                amount,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 30,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.7,
                ),
              ),
              const SizedBox(height: 3),
              const Text(
                'Actividad estimada del día',
                style: TextStyle(
                  color: Color(0xFFDDE7F3),
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: _HeroSmallMetric(value: quotesToday, label: 'Hoy'),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _HeroSmallMetric(
                      value: pending,
                      label: 'Pendientes',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _HeroSmallMetric(value: completed, label: 'Listos'),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _WeeklyActivityCard extends StatelessWidget {
  final List<dynamic> weekChart;
  final List<int> fallbackValues;

  const _WeeklyActivityCard({
    required this.weekChart,
    required this.fallbackValues,
  });

  @override
  Widget build(BuildContext context) {
    final values = weekChart.isNotEmpty
        ? weekChart.map((item) {
            if (item is Map) {
              return int.tryParse((item['count'] ?? '0').toString()) ?? 0;
            }
            return 0;
          }).toList()
        : fallbackValues;

    final labels = weekChart.isNotEmpty
        ? weekChart.map((item) {
            if (item is Map) return (item['label'] ?? '').toString();
            return '';
          }).toList()
        : const ['L', 'M', 'M', 'J', 'V', 'S', 'D'];

    final maxValue = values.isEmpty
        ? 1
        : values.reduce((a, b) => a > b ? a : b);
    final safeMax = maxValue <= 0 ? 1 : maxValue;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: const Color(0xFFDDE7F3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Pulso operativo',
            style: TextStyle(
              color: PactopiaTheme.text,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Movimientos recientes de tu operación.',
            style: TextStyle(
              color: PactopiaTheme.muted,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 132,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: List.generate(values.length, (index) {
                final value = values[index];
                final height = 28 + ((value / safeMax) * 82);

                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        AnimatedContainer(
                          duration: const Duration(milliseconds: 350),
                          height: height,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(999),
                            gradient: const LinearGradient(
                              colors: [
                                Color(0xFF2563EB),
                                Color(0xFF06B6D4),
                                Color(0xFF7C3AED),
                              ],
                              begin: Alignment.bottomCenter,
                              end: Alignment.topCenter,
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          labels[index].isEmpty ? '-' : labels[index],
                          style: const TextStyle(
                            color: PactopiaTheme.muted,
                            fontWeight: FontWeight.w800,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }),
            ),
          ),
        ],
      ),
    );
  }
}

class _SmartActionCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final String buttonText;
  final IconData icon;
  final VoidCallback onTap;

  const _SmartActionCard({
    required this.title,
    required this.subtitle,
    required this.buttonText,
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: const Color(0xFFDDE7F3)),
      ),
      child: Row(
        children: [
          _GradientIconBox(icon: icon),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: PactopiaTheme.text,
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: PactopiaTheme.muted,
                    fontWeight: FontWeight.w600,
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  height: 44,
                  child: PactopiaGradientButton(
                    onPressed: onTap,
                    icon: const Icon(
                      Icons.arrow_forward_rounded,
                      color: Colors.white,
                      size: 19,
                    ),
                    label: buttonText,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MobileHomeTab extends StatelessWidget {
  final String title;
  final String email;
  final String plan;
  final String status;
  final String nextPayment;
  final String healthStatus;
  final String healthMessage;
  final int activeModules;
  final int blockedModules;
  final String rfcs;
  final String quotes;
  final String xml;
  final String files;
  final List<Map<String, dynamic>> quickActions;
  final Map<String, dynamic> homeInsights;
  final VoidCallback onRefresh;
  final VoidCallback onLogout;
  final void Function({String? initialRfc}) onOpenQuotes;
  final ValueChanged<String> onOpenAction;

  const _MobileHomeTab({
    required this.title,
    required this.email,
    required this.plan,
    required this.status,
    required this.nextPayment,
    required this.healthStatus,
    required this.healthMessage,
    required this.activeModules,
    required this.blockedModules,
    required this.rfcs,
    required this.quotes,
    required this.xml,
    required this.files,
    required this.quickActions,
    required this.homeInsights,
    required this.onRefresh,
    required this.onLogout,
    required this.onOpenQuotes,
    required this.onOpenAction,
  });

  Map<String, dynamic> _map(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) {
      return value.map((key, val) => MapEntry(key.toString(), val));
    }
    return <String, dynamic>{};
  }

  List<dynamic> _list(dynamic value) {
    if (value is List) return value;
    return <dynamic>[];
  }

  @override
  Widget build(BuildContext context) {
    final today = _map(homeInsights['today']);
    final primaryAction = _map(homeInsights['primary_action']);
    final weekChart = _list(homeInsights['week_chart']);

    final todayAmount = (today['amount_label'] ?? '\$0.00 MXN').toString();
    final todayQuotes = (today['quotes_count'] ?? quotes).toString();
    final pendingQuotes = (today['pending_quotes'] ?? '0').toString();
    final completedQuotes = (today['completed_quotes'] ?? '0').toString();

    final actionKey = (primaryAction['key'] ?? 'sat').toString();
    final actionTitle = (primaryAction['title'] ?? 'Continuar operación')
        .toString();
    final actionText =
        (primaryAction['text'] ?? 'Revisa tus pendientes y actividad del día.')
            .toString();
    final actionLabel = (primaryAction['label'] ?? 'Abrir').toString();

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      children: [
        _MobileTopBar(
          title: 'Inicio',
          subtitle: 'Resumen del día',
          onRefresh: onRefresh,
          onLogout: onLogout,
        ),
        const SizedBox(height: 14),

        _TodayOverviewCard(
          title: title,
          email: email,
          plan: plan,
          status: status,
          amount: todayAmount,
          quotesToday: todayQuotes,
          pending: pendingQuotes,
          completed: completedQuotes,
        ),

        const SizedBox(height: 12),
        _CompactHealthBanner(status: healthStatus, message: healthMessage),

        const SizedBox(height: 16),
        _SectionHeaderCompact(
          title: 'Actividad semanal',
          subtitle: 'Vista rápida para entender cómo va tu operación.',
        ),
        const SizedBox(height: 10),
        _WeeklyActivityCard(
          weekChart: weekChart,
          fallbackValues: [
            int.tryParse(quotes) ?? 0,
            int.tryParse(rfcs) ?? 0,
            int.tryParse(xml) ?? 0,
            int.tryParse(files) ?? 0,
            activeModules,
            blockedModules,
            int.tryParse(pendingQuotes) ?? 0,
          ],
        ),

        const SizedBox(height: 16),
        _SmartActionCard(
          title: actionTitle,
          subtitle: actionText,
          buttonText: actionLabel,
          icon: _dashboardIcon(actionKey),
          onTap: () => onOpenAction(actionKey),
        ),

        const SizedBox(height: 16),
        _SectionHeaderCompact(
          title: 'Indicadores clave',
          subtitle: 'Datos compactos para decidir rápido.',
        ),
        const SizedBox(height: 10),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 1.42,
          children: [
            _MiniDashboardCard(
              title: 'Módulos',
              value: '$activeModules',
              icon: Icons.apps_rounded,
            ),
            _MiniDashboardCard(
              title: 'RFCs',
              value: rfcs,
              icon: Icons.badge_rounded,
            ),
            _MiniDashboardCard(
              title: 'SAT pendientes',
              value: pendingQuotes,
              icon: Icons.pending_actions_rounded,
            ),
            _MiniDashboardCard(
              title: 'XML / Archivos',
              value: xml == '0' ? files : xml,
              icon: Icons.folder_special_rounded,
            ),
          ],
        ),

        const SizedBox(height: 16),
        _SectionHeaderCompact(
          title: 'Accesos rápidos',
          subtitle: 'Lo que más usa el cliente en móvil.',
        ),
        const SizedBox(height: 10),
        GridView.builder(
          itemCount: quickActions.isEmpty ? 4 : quickActions.length,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 1.75,
          ),
          itemBuilder: (context, index) {
            final fallback = [
              {'key': 'sat', 'label': 'SAT', 'icon': 'sat'},
              {'key': 'pay', 'label': 'Pagar', 'icon': 'payments'},
              {'key': 'invoices', 'label': 'Facturas', 'icon': 'receipt'},
              {'key': 'account', 'label': 'Mi cuenta', 'icon': 'person'},
            ];

            final item = quickActions.isEmpty
                ? fallback[index]
                : quickActions[index];
            final key = (item['key'] ?? '').toString();

            return _CompactActionTile(
              title: (item['label'] ?? 'Acción').toString(),
              icon: _dashboardIcon((item['icon'] ?? key).toString()),
              onTap: () => onOpenAction(key),
            );
          },
        ),
      ],
    );
  }
}

class _MobileBillingHubTab extends StatelessWidget {
  final String plan;
  final String status;
  final String accountName;
  final String accountRfc;
  final VoidCallback onOpenStatement;
  final VoidCallback onOpenInvoices;
  final VoidCallback onOpenProfile;

  const _MobileBillingHubTab({
    required this.plan,
    required this.status,
    required this.accountName,
    required this.accountRfc,
    required this.onOpenStatement,
    required this.onOpenInvoices,
    required this.onOpenProfile,
  });

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      children: [
        const _MobileTopBarStatic(
          title: 'Facturación',
          subtitle: 'CFDI, pagos, facturas y cuenta.',
        ),
        const SizedBox(height: 14),
        _ModuleHeroPanel(
          title: 'Centro de facturación',
          subtitle: 'Todo lo relacionado con pagos, facturas y datos fiscales.',
          icon: Icons.receipt_long_rounded,
          gradient: const [
            Color(0xFF2563EB),
            Color(0xFF06B6D4),
            Color(0xFF7C3AED),
          ],
        ),
        const SizedBox(height: 14),
        _BillingStatusCard(
          plan: plan,
          status: status,
          accountName: accountName,
          accountRfc: accountRfc,
        ),
        const SizedBox(height: 16),
        _SectionHeaderCompact(
          title: 'Acciones de facturación',
          subtitle: 'Lo que el cliente necesita encontrar rápido.',
        ),
        const SizedBox(height: 10),
        _ActionListTile(
          icon: Icons.account_balance_wallet_rounded,
          title: 'Estado de cuenta',
          subtitle: 'Consulta saldos, periodos, PDF y pagos.',
          onTap: onOpenStatement,
        ),
        _ActionListTile(
          icon: Icons.description_rounded,
          title: 'Facturas Pactopia',
          subtitle: 'Descarga facturas disponibles y solicitudes.',
          onTap: onOpenInvoices,
        ),
        _ActionListTile(
          icon: Icons.person_rounded,
          title: 'Datos fiscales',
          subtitle: 'Revisa información de la cuenta cliente.',
          onTap: onOpenProfile,
        ),
        const SizedBox(height: 16),
        _ComingSoonPanel(
          title: 'Siguiente fase',
          text:
              'Aquí conectaremos Nuevo CFDI, Receptores, Borradores, Emitidas, Cancelaciones y timbres disponibles cuando terminemos el módulo de facturación móvil.',
        ),
      ],
    );
  }
}

class _MobileSatHubTab extends StatelessWidget {
  final String quotes;
  final String rfcs;
  final String sources;
  final String xml;
  final String files;
  final String vaultUsed;
  final String vaultAvailable;
  final void Function({String? initialRfc}) onOpenQuotes;

  const _MobileSatHubTab({
    required this.quotes,
    required this.rfcs,
    required this.sources,
    required this.xml,
    required this.files,
    required this.vaultUsed,
    required this.vaultAvailable,
    required this.onOpenQuotes,
  });

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      children: [
        const _MobileTopBarStatic(
          title: 'SAT',
          subtitle: 'Cotizaciones, descargas y bóveda fiscal.',
        ),
        const SizedBox(height: 14),
        _ModuleHeroPanel(
          title: 'SAT Descargas',
          subtitle: 'Consulta cotizaciones, pagos y seguimiento operativo.',
          icon: Icons.cloud_download_rounded,
          gradient: const [
            Color(0xFF0F2344),
            Color(0xFF2563EB),
            Color(0xFF06B6D4),
          ],
        ),
        const SizedBox(height: 14),
        _PriorityActionCard(
          title: 'Cotizaciones SAT',
          subtitle: 'Filtra, consulta estados, paga o sube comprobantes.',
          icon: Icons.receipt_long_rounded,
          buttonText: 'Entrar a cotizaciones',
          onTap: () => onOpenQuotes(),
        ),
        const SizedBox(height: 14),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 1.42,
          children: [
            _MiniDashboardCard(
              title: 'Cotizaciones',
              value: quotes,
              icon: Icons.receipt_rounded,
            ),
            _MiniDashboardCard(
              title: 'RFCs',
              value: rfcs,
              icon: Icons.badge_rounded,
            ),
            _MiniDashboardCard(
              title: 'Fuentes',
              value: sources,
              icon: Icons.source_rounded,
            ),
            _MiniDashboardCard(
              title: 'XML',
              value: xml,
              icon: Icons.data_object_rounded,
            ),
          ],
        ),
        const SizedBox(height: 14),
        _StoragePanel(used: vaultUsed, available: vaultAvailable, files: files),
      ],
    );
  }
}

class _MobileModulesTab extends StatelessWidget {
  final List<Map<String, dynamic>> modules;
  final ValueChanged<Map<String, dynamic>> onOpenModule;

  const _MobileModulesTab({required this.modules, required this.onOpenModule});

  @override
  Widget build(BuildContext context) {
    final active = modules
        .where(
          (m) => (m['state'] ?? 'active').toString().toLowerCase() == 'active',
        )
        .toList(growable: false);

    final locked = modules
        .where((m) => (m['state'] ?? '').toString().toLowerCase() == 'blocked')
        .toList(growable: false);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      children: [
        const _MobileTopBarStatic(
          title: 'Módulos',
          subtitle: 'Tu ecosistema Pactopia360.',
        ),
        const SizedBox(height: 14),
        _ModuleHeroPanel(
          title: 'Centro de módulos',
          subtitle: 'Administra tus herramientas por prioridad y estado.',
          icon: Icons.apps_rounded,
          gradient: const [
            Color(0xFF07111F),
            Color(0xFF0F2344),
            Color(0xFF7C3AED),
          ],
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(
              child: _SmallCountPill(
                label: 'Activos',
                value: '${active.length}',
                icon: Icons.check_circle_rounded,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _SmallCountPill(
                label: 'Bloqueados',
                value: '${locked.length}',
                icon: Icons.lock_rounded,
              ),
            ),
          ],
        ),
        const SizedBox(height: 18),
        _SectionHeaderCompact(
          title: 'Disponibles',
          subtitle: 'Abre el módulo que necesitas usar.',
        ),
        const SizedBox(height: 10),
        if (modules.isEmpty)
          const _EmptyPanel(text: 'No hay módulos visibles para esta cuenta.')
        else
          ...modules.map((module) {
            final title = (module['name'] ?? 'Módulo').toString();
            final key = (module['key'] ?? '').toString();
            final state = (module['state'] ?? 'active').toString();
            final access = module['access'] == true;
            final enabled = access && state.toLowerCase() == 'active';

            return _ModernModuleTile(
              title: title,
              subtitle: enabled ? 'Disponible para usar' : 'Acceso restringido',
              icon: _dashboardIcon(key),
              state: state,
              enabled: enabled,
              onTap: () => onOpenModule(module),
            );
          }),
      ],
    );
  }
}

class _MobileAccountTab extends StatelessWidget {
  final String title;
  final String email;
  final String plan;
  final String status;
  final String nextPayment;
  final String accountName;
  final String accountRfc;
  final VoidCallback onRefresh;
  final VoidCallback onLogout;
  final VoidCallback onOpenProfile;
  final VoidCallback onOpenPayments;
  final VoidCallback onOpenInvoices;
  final VoidCallback onOpenStatement;

  const _MobileAccountTab({
    required this.title,
    required this.email,
    required this.plan,
    required this.status,
    required this.nextPayment,
    required this.accountName,
    required this.accountRfc,
    required this.onRefresh,
    required this.onLogout,
    required this.onOpenProfile,
    required this.onOpenPayments,
    required this.onOpenInvoices,
    required this.onOpenStatement,
  });

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      children: [
        _MobileTopBar(
          title: 'Cuenta',
          subtitle: 'Perfil y administración',
          onRefresh: onRefresh,
          onLogout: onLogout,
        ),
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(26),
            border: Border.all(color: const Color(0xFFDDE7F3)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x1107111F),
                blurRadius: 20,
                offset: Offset(0, 10),
              ),
            ],
          ),
          child: Row(
            children: [
              const PactopiaLogoMark(size: 58),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: PactopiaTheme.text,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      email.isEmpty ? 'Sin correo registrado' : email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: PactopiaTheme.muted,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _BillingStatusCard(
          plan: plan,
          status: status,
          accountName: accountName,
          accountRfc: accountRfc,
        ),
        const SizedBox(height: 14),
        _ActionListTile(
          icon: Icons.person_rounded,
          title: 'Mi perfil',
          subtitle: 'Datos de usuario y cuenta.',
          onTap: onOpenProfile,
        ),
        _ActionListTile(
          icon: Icons.payments_rounded,
          title: 'Pagos',
          subtitle: 'Historial y movimientos.',
          onTap: onOpenPayments,
        ),
        _ActionListTile(
          icon: Icons.description_rounded,
          title: 'Facturas',
          subtitle: 'Documentos disponibles.',
          onTap: onOpenInvoices,
        ),
        _ActionListTile(
          icon: Icons.account_balance_wallet_rounded,
          title: 'Estado de cuenta',
          subtitle: nextPayment.isEmpty
              ? 'Cobro sin fecha definida.'
              : 'Próximo cobro: $nextPayment',
          onTap: onOpenStatement,
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: onLogout,
          icon: const Icon(Icons.logout_rounded),
          label: const Text('Cerrar sesión'),
        ),
      ],
    );
  }
}

class _MobileTopBar extends StatelessWidget {
  final String title;
  final String subtitle;
  final VoidCallback onRefresh;
  final VoidCallback onLogout;

  const _MobileTopBar({
    required this.title,
    required this.subtitle,
    required this.onRefresh,
    required this.onLogout,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const PactopiaLogoMark(size: 42),
        const SizedBox(width: 12),
        Expanded(
          child: _TopTitle(title: title, subtitle: subtitle),
        ),
        _RoundIconButton(icon: Icons.refresh_rounded, onTap: onRefresh),
        const SizedBox(width: 8),
        _RoundIconButton(icon: Icons.logout_rounded, onTap: onLogout),
      ],
    );
  }
}

class _MobileTopBarStatic extends StatelessWidget {
  final String title;
  final String subtitle;

  const _MobileTopBarStatic({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const PactopiaLogoMark(size: 42),
        const SizedBox(width: 12),
        Expanded(
          child: _TopTitle(title: title, subtitle: subtitle),
        ),
      ],
    );
  }
}

class _TopTitle extends StatelessWidget {
  final String title;
  final String subtitle;

  const _TopTitle({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: PactopiaTheme.text,
            fontSize: 22,
            fontWeight: FontWeight.w900,
            letterSpacing: -0.4,
          ),
        ),
        Text(
          subtitle,
          style: const TextStyle(
            color: PactopiaTheme.muted,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _RoundIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _RoundIconButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: const BorderSide(color: Color(0xFFDDE7F3)),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(icon, color: PactopiaTheme.navy2, size: 21),
        ),
      ),
    );
  }
}

class _ExecutiveHeroCard extends StatelessWidget {
  final String title;
  final String email;
  final String plan;
  final String status;
  final String nextPayment;
  final int activeModules;
  final int blockedModules;

  const _ExecutiveHeroCard({
    required this.title,
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
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        gradient: const LinearGradient(
          colors: [Color(0xFF07111F), Color(0xFF0F2344), Color(0xFF2563EB)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x330F2344),
            blurRadius: 26,
            offset: Offset(0, 14),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -46,
            top: -46,
            child: Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.10),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const PactopiaLogoMark(size: 52, light: true),
                  const Spacer(),
                  _GlassPill(text: plan),
                ],
              ),
              const SizedBox(height: 18),
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.6,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                email.isEmpty ? 'Portal cliente móvil' : email,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Color(0xFFE2E8F0),
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _GlassPill(text: 'Estado: $status'),
                  _GlassPill(
                    text: nextPayment.isEmpty
                        ? 'Cobro sin fecha'
                        : 'Cobro: $nextPayment',
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: _HeroSmallMetric(
                      value: '$activeModules',
                      label: 'Activos',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _HeroSmallMetric(
                      value: '$blockedModules',
                      label: 'Bloqueados',
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GlassPill extends StatelessWidget {
  final String text;

  const _GlassPill({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.14),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withOpacity(0.22)),
      ),
      child: Text(
        text,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w800,
          fontSize: 12.5,
        ),
      ),
    );
  }
}

class _HeroSmallMetric extends StatelessWidget {
  final String value;
  final String label;

  const _HeroSmallMetric({required this.value, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.13),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withOpacity(0.20)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 21,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(
              color: Color(0xFFE2E8F0),
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _CompactHealthBanner extends StatelessWidget {
  final String status;
  final String message;

  const _CompactHealthBanner({required this.status, required this.message});

  @override
  Widget build(BuildContext context) {
    final isOk = status.toLowerCase() == 'ok';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isOk ? const Color(0xFFE9FBF3) : const Color(0xFFFFF1F2),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: isOk ? const Color(0xFFB7F0D3) : const Color(0xFFFFCDD2),
        ),
      ),
      child: Row(
        children: [
          Icon(
            isOk ? Icons.verified_rounded : Icons.warning_rounded,
            color: isOk ? const Color(0xFF047857) : const Color(0xFFBE123C),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: isOk ? const Color(0xFF065F46) : const Color(0xFF9F1239),
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionHeaderCompact extends StatelessWidget {
  final String title;
  final String subtitle;

  const _SectionHeaderCompact({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: PactopiaTheme.text,
            fontSize: 19,
            fontWeight: FontWeight.w900,
            letterSpacing: -0.2,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          subtitle,
          style: const TextStyle(
            color: PactopiaTheme.muted,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _PriorityActionCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final String buttonText;
  final VoidCallback onTap;

  const _PriorityActionCard({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.buttonText,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: const Color(0xFFDDE7F3)),
      ),
      child: Column(
        children: [
          Row(
            children: [
              _GradientIconBox(icon: icon),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        color: PactopiaTheme.text,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        color: PactopiaTheme.muted,
                        fontWeight: FontWeight.w600,
                        height: 1.3,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: PactopiaGradientButton(
              onPressed: onTap,
              icon: const Icon(
                Icons.arrow_forward_rounded,
                color: Colors.white,
              ),
              label: buttonText,
            ),
          ),
        ],
      ),
    );
  }
}

class _HorizontalMetricCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;

  const _HorizontalMetricCard({
    required this.title,
    required this.value,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 132,
      margin: const EdgeInsets.only(right: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFDDE7F3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: PactopiaTheme.navy2, size: 22),
          const Spacer(),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: PactopiaTheme.text,
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            title,
            style: const TextStyle(
              color: PactopiaTheme.muted,
              fontWeight: FontWeight.w700,
              fontSize: 12.5,
            ),
          ),
        ],
      ),
    );
  }
}

class _CompactActionTile extends StatelessWidget {
  final String title;
  final IconData icon;
  final VoidCallback onTap;

  const _CompactActionTile({
    required this.title,
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(24),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: const Color(0xFFDDE7F3)),
          ),
          child: Row(
            children: [
              _SoftIconBox(icon: icon),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: PactopiaTheme.text,
                    fontWeight: FontWeight.w900,
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

class _ModuleHeroPanel extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final List<Color> gradient;

  const _ModuleHeroPanel({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.gradient,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: LinearGradient(
          colors: gradient,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x220F2344),
            blurRadius: 22,
            offset: Offset(0, 12),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.14),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withOpacity(0.20)),
            ),
            child: Icon(icon, color: Colors.white, size: 30),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: Color(0xFFE2E8F0),
                    fontWeight: FontWeight.w600,
                    height: 1.3,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BillingStatusCard extends StatelessWidget {
  final String plan;
  final String status;
  final String accountName;
  final String accountRfc;

  const _BillingStatusCard({
    required this.plan,
    required this.status,
    required this.accountName,
    required this.accountRfc,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFDDE7F3)),
      ),
      child: Column(
        children: [
          _KeyRowModern(label: 'Plan actual', value: plan),
          _KeyRowModern(label: 'Estado', value: status),
          if (accountName.isNotEmpty)
            _KeyRowModern(label: 'Cuenta', value: accountName),
          if (accountRfc.isNotEmpty)
            _KeyRowModern(label: 'RFC', value: accountRfc),
        ],
      ),
    );
  }
}

class _ActionListTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _ActionListTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(24),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: const Color(0xFFDDE7F3)),
            ),
            child: Row(
              children: [
                _SoftIconBox(icon: icon),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: PactopiaTheme.text,
                          fontWeight: FontWeight.w900,
                          fontSize: 15.5,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: PactopiaTheme.muted,
                          fontWeight: FontWeight.w600,
                          height: 1.25,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: PactopiaTheme.muted,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ComingSoonPanel extends StatelessWidget {
  final String title;
  final String text;

  const _ComingSoonPanel({required this.title, required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF2FF),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFCFE0FF)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.rocket_launch_rounded, color: PactopiaTheme.blue),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: PactopiaTheme.navy2,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  text,
                  style: const TextStyle(
                    color: PactopiaTheme.navy2,
                    fontWeight: FontWeight.w600,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MiniDashboardCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;

  const _MiniDashboardCard({
    required this.title,
    required this.value,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFDDE7F3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SoftIconBox(icon: icon),
          const Spacer(),
          Text(
            value,
            style: const TextStyle(
              color: PactopiaTheme.text,
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            title,
            style: const TextStyle(
              color: PactopiaTheme.muted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _StoragePanel extends StatelessWidget {
  final String used;
  final String available;
  final String files;

  const _StoragePanel({
    required this.used,
    required this.available,
    required this.files,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: const Color(0xFFDDE7F3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Bóveda fiscal',
            style: TextStyle(
              color: PactopiaTheme.text,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          _KeyRowModern(label: 'Usado', value: used),
          _KeyRowModern(label: 'Disponible', value: available),
          _KeyRowModern(label: 'Archivos recientes', value: files),
        ],
      ),
    );
  }
}

class _SmallCountPill extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;

  const _SmallCountPill({
    required this.label,
    required this.value,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFDDE7F3)),
      ),
      child: Row(
        children: [
          _SoftIconBox(icon: icon),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  value,
                  style: const TextStyle(
                    color: PactopiaTheme.text,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  label,
                  style: const TextStyle(
                    color: PactopiaTheme.muted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ModernModuleTile extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final String state;
  final bool enabled;
  final VoidCallback onTap;

  const _ModernModuleTile({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.state,
    required this.enabled,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final normalized = state.trim().toLowerCase();
    final badgeText = normalized == 'blocked'
        ? 'Bloqueado'
        : normalized == 'inactive'
        ? 'Inactivo'
        : 'Activo';

    final badgeColor = normalized == 'blocked'
        ? const Color(0xFFBE123C)
        : normalized == 'inactive'
        ? const Color(0xFFD97706)
        : const Color(0xFF047857);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(24),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: const Color(0xFFDDE7F3)),
            ),
            child: Row(
              children: [
                enabled
                    ? _GradientIconBox(icon: icon)
                    : _SoftIconBox(icon: icon),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: PactopiaTheme.text,
                          fontWeight: FontWeight.w900,
                          fontSize: 15.5,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: PactopiaTheme.muted,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: badgeColor.withOpacity(0.11),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    badgeText,
                    style: TextStyle(
                      color: badgeColor,
                      fontWeight: FontWeight.w900,
                      fontSize: 11.5,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _EmptyPanel extends StatelessWidget {
  final String text;

  const _EmptyPanel({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFDDE7F3)),
      ),
      child: Text(
        text,
        textAlign: TextAlign.center,
        style: const TextStyle(
          color: PactopiaTheme.muted,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _KeyRowModern extends StatelessWidget {
  final String label;
  final String value;

  const _KeyRowModern({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 11),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                color: PactopiaTheme.muted,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value.isEmpty ? 'N/D' : value,
              textAlign: TextAlign.right,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: PactopiaTheme.text,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _GradientIconBox extends StatelessWidget {
  final IconData icon;

  const _GradientIconBox({required this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 48,
      height: 48,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: const LinearGradient(
          colors: [Color(0xFF2563EB), Color(0xFF06B6D4), Color(0xFF7C3AED)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Icon(icon, color: Colors.white, size: 23),
    );
  }
}

class _SoftIconBox extends StatelessWidget {
  final IconData icon;

  const _SoftIconBox({required this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: const Color(0xFFEAF2FF),
        borderRadius: BorderRadius.circular(17),
      ),
      child: Icon(icon, color: PactopiaTheme.blue, size: 22),
    );
  }
}

IconData _dashboardIcon(String key) {
  switch (key.trim().toLowerCase()) {
    case 'receipt':
    case 'facturacion':
    case 'invoices':
    case 'facturas':
      return Icons.receipt_long_rounded;
    case 'sat':
    case 'sat_descargas':
    case 'cloud':
      return Icons.cloud_download_rounded;
    case 'payments':
    case 'pay':
    case 'pagos':
      return Icons.payments_rounded;
    case 'account':
    case 'profile':
    case 'person':
    case 'mi_cuenta':
      return Icons.person_rounded;
    case 'estado_cuenta':
    case 'account_balance':
      return Icons.account_balance_wallet_rounded;
    case 'crm':
    case 'people':
      return Icons.groups_rounded;
    case 'inventario':
    case 'inventory':
      return Icons.inventory_2_rounded;
    case 'ventas':
    case 'point_of_sale':
      return Icons.point_of_sale_rounded;
    case 'reportes':
    case 'bar_chart':
      return Icons.bar_chart_rounded;
    case 'recursos_humanos':
      return Icons.badge_rounded;
    case 'timbres_hits':
      return Icons.local_activity_rounded;
    case 'boveda_fiscal':
    case 'storage':
      return Icons.folder_special_rounded;
    default:
      return Icons.apps_rounded;
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

  const _QuoteCard({
    required this.quote,
    required this.onTap,
    this.compact = false,
  });

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
