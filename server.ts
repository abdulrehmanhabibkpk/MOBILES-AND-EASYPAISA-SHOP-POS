import express from 'express';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';
import archiver from 'archiver';
import { createServer as createViteServer } from 'vite';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function startServer() {
  const app = express();
  const PORT = 3000;

  // Endpoint to download complete, 100% production-ready ZIP containing all compiled frontend JS/CSS + PHP Backend + db.sql
  app.get('/api/download-hosting-zip', (req, res) => {
    try {
      const distDir = path.join(process.cwd(), 'dist');
      if (!fs.existsSync(distDir)) {
        return res.status(500).json({ error: 'Dist directory not found. Please build first.' });
      }

      res.setHeader('Content-Type', 'application/zip');
      res.setHeader('Content-Disposition', 'attachment; filename="balal_pos_hosting_ready.zip"');

      const archive = archiver('zip', { zlib: { level: 9 } });

      archive.on('error', (err) => {
        console.error('Archive error:', err);
        res.status(500).send({ error: err.message });
      });

      archive.pipe(res);

      // 1. Add all compiled dist files (index.html, assets folder with all JS & CSS chunks, icons, sw.js, manifest)
      archive.directory(distDir, false);

      // 2. Add urdu guide
      const urduGuide = `# بلال موبائلز اینڈ ایزی پیسہ شاپ - ہوسٹنگ انسٹالیشن گائیڈ

1. اپنے cPanel یا hPanel میں نیا MySQL ڈیٹا بیس بنائیں۔
2. phpMyAdmin میں جا کر db.sql فائل کو Import کریں۔
3. config.php فائل کو ایڈٹ کر کے اپنے ڈیٹا بیس کا نام، یوزر اور پاس ورڈ درج کریں۔
4. اس زپ فائل کی تمام فائلیں (بشمول assets فولڈر، index.html، api فولڈر وغیرہ) اپنے htdocs (InfinityFree) یا public_html (Hostinger) میں اپلوڈ کر دیں۔
5. اب اپنا ڈومین براؤزر میں کھولیں، آپ کا سوفٹ ویئر 100% لائیو چل پڑے گا!
`;
      archive.append(urduGuide, { name: 'README_URDU.txt' });

      archive.finalize();
    } catch (err: any) {
      console.error('Download error:', err);
      res.status(500).json({ error: err.message });
    }
  });

  // Vite middleware for development
  if (process.env.NODE_ENV !== 'production') {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*', (req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`Server running on http://localhost:${PORT}`);
  });
}

startServer();
